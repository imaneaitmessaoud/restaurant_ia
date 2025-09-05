<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqOrderAgent
{
    public function __construct(
        private HttpClientInterface $http,
        private ?string $apiKey = null,
        private ?string $model  = null,
    ) {
        $this->apiKey = $this->apiKey ?? ($_ENV['GROQ_API_KEY'] ?? '');
        $this->model  = $this->model  ?? ($_ENV['GROQ_MODEL'] ?? 'llama-3.3-70b-versatile');
    }

    /**
     * Retourne soit un payload structuré (conversation/order/actions/assistant_text),
     * soit un tableau minimal ["assistant_text" => "..."] si le modèle répond en texte libre.
     */
    public function infer(array $ctx): array
    {
        $system   = (string)($ctx['system_prompt'] ?? $this->defaultSystemPrompt());
        $from     = (string)($ctx['from'] ?? 'unknown');
        $message  = (string)($ctx['user_text'] ?? '');
        $menu     = $ctx['menu'] ?? [];
        $catalogs = $ctx['catalogs'] ?? [];
        $state    = $ctx['state'] ?? null;
        $systemPrompt .= "\nPour les articles personnalisables, demande obligatoirement les personnalisations requises AVANT de passer au type de service. Les personnalisations sont : taille, pâte, supplément, etc.";

        // On envoie le "contexte" dans un seul objet JSON côté user pour aider le modèle
        $userEnvelope = json_encode([
            "from"     => $from,
            "state"    => $state,
            "menu"     => $menu,
            "catalogs" => $catalogs,
            "message"  => $message
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        // Instruction "JSON only" : demander STRICTEMENT du JSON (sans markdown)
        $jsonInstruction = <<<TXT
Réponds STRICTEMENT en JSON valide, sans code block, sans commentaire, au format :
{
  "assistant_text": "string court, même langue que l'utilisateur",
  "conversation": {
    "from": "string",
    "language": "fr|ar|en|darija",
    "status": "draft|await_service|await_address|await_confirm|confirmed|cancelled"
  },
  "order": {
    "items": [
      {
        "name": "string",
        "quantity": 1,
        "final_unit_price": 0,
        "base_unit_price": 0,
        "delta_unit_price": 0,
        "customizations": {} 
      }
    ],
    "service_type": "SUR_PLACE|EMPORTER|LIVRAISON",
    "delivery_address": "string",
    "customer": { "name":"string", "phone":"string" },
    "currency": "MAD",
    "totals": { "items_subtotal": 0, "delivery_fee": 0, "grand_total": 0 }
  },
  "actions": {
    "need_service_type": false,
    "need_address": false,
    "need_customer_info": false,
    "ready_for_confirmation": false,
    "confirmed": false,
    "cancelled": false
  }
}
- N'inclus AUCUN texte hors JSON.
- Si tu n'es pas sûr d'une valeur, mets-la à vide ou omets-la.
- Calcule grand_total = somme(items) + delivery_fee si possible.
TXT;

        $messages = [
            ["role" => "system", "content" => $system."\n\n".$jsonInstruction],
            ["role" => "user",   "content" => $userEnvelope],
        ];

        $payload = [
            "model" => $this->model,
            "messages" => $messages,
            "temperature" => 0.2,
            "max_tokens"  => 800
        ];

        try {
            return $this->doChat($payload);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'model_decommissioned')) {
                // Fallback modèles connus
                foreach (['llama-3.3-70b-versatile', 'llama-3.1-8b-instant'] as $m) {
                    if ($m === $this->model) continue;
                    $payload['model'] = $m;
                    try {
                        $out = $this->doChat($payload);
                        $this->model = $m;
                        return $out;
                    } catch (\RuntimeException) { /* try next */ }
                }
            }
            throw $e;
        }
    }

    private function doChat(array $payload): array
    {
        $res = $this->http->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type'  => 'application/json'
            ],
            'json' => $payload,
            'timeout' => 40
        ]);

        $status = $res->getStatusCode();
        $raw    = $res->getContent(false);
        if ($status >= 400) {
            throw new \RuntimeException("Groq HTTP $status: ".$raw);
        }
        $data = json_decode($raw, true);
        $txt  = $data['choices'][0]['message']['content'] ?? "";

        // Essaie de parser du JSON strict
        $decoded = json_decode($txt, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Sinon renvoie un assistant_text simple
        return ["assistant_text" => ($txt ?: "Désolé, je n'ai pas pu comprendre.")];
    }

    private function defaultSystemPrompt(): string
    {
        return "You are a helpful multilingual restaurant ordering assistant. 
- Understand darija, Arabic, French, English.
- Ask only for the missing info (service type, address for delivery, name/phone for confirmation).
- Keep messages short and friendly.";
    }
}
