<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiOrderAgentTools
{
    public function __construct(
        private HttpClientInterface $http,
        private ?string $apiKey = null,
        private ?string $model  = null,
    ) {
        $this->apiKey = $this->apiKey ?? ($_ENV['OPENAI_API_KEY'] ?? '');
        $this->model  = $this->model  ?? ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini');
    }

    public function infer(array $ctx): array
    {
        $system   = (string)($ctx['system_prompt'] ?? 'You are a multilingual restaurant ordering assistant. Respond in user language (darija, Arabic, French, English). Keep replies short.');
        $menu     = $ctx['menu'] ?? self::defaultMenu();
        $catalogs = $ctx['catalogs'] ?? [];
        $from     = (string)($ctx['from'] ?? 'unknown');
        $message  = (string)($ctx['user_text'] ?? '');
        $state    = $ctx['state'] ?? null;

        // ✅ NOUVEAU FORMAT tools : name/description/parameters au niveau racine
        $tools = [[
            "type" => "function",
            "name" => "submit_order",
            "description" => "Finalize or update a restaurant order based on the conversation.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "assistant_text" => ["type" => "string", "description" => "Short reply to user (no sensitive details)."],
                    "conversation" => [
                        "type" => "object",
                        "properties" => [
                            "from"     => ["type" => "string"],
                            "language" => ["type" => "string", "enum" => ["fr","ar","en","darija"]],
                            "status"   => ["type" => "string", "enum" => ["draft","await_service","await_address","await_confirm","confirmed","cancelled"]]
                        ],
                        "required" => ["from","language","status"]
                    ],
                    "order" => [
                        "type" => "object",
                        "properties" => [
                            "items" => [
                                "type" => "array",
                                "items" => [
                                    "type" => "object",
                                    "properties" => [
                                        "name"             => ["type" => "string"],
                                        "quantity"         => ["type" => "integer", "minimum" => 1],
                                        "base_unit_price"  => ["type" => "number"],
                                        "customizations"   => ["type" => "object", "additionalProperties" => true],
                                        "delta_unit_price" => ["type" => "number", "default" => 0],
                                        "final_unit_price" => ["type" => "number"]
                                    ],
                                    "required" => ["name","quantity","final_unit_price"]
                                ]
                            ],
                            "service_type"     => ["type" => "string", "enum" => ["SUR_PLACE","EMPORTER","LIVRAISON"]],
                            "delivery_address" => ["type" => "string"],
                            "customer" => [
                                "type" => "object",
                                "properties" => [
                                    "name"  => ["type" => "string"],
                                    "phone" => ["type" => "string"]
                                ]
                            ],
                            "currency" => ["type" => "string", "default" => "MAD"],
                            "totals" => [
                                "type" => "object",
                                "properties" => [
                                    "items_subtotal" => ["type" => "number"],
                                    "delivery_fee"   => ["type" => "number", "default" => 0],
                                    "grand_total"    => ["type" => "number"]
                                ]
                            ]
                        ],
                        "required" => ["items"]
                    ],
                    "actions" => [
                        "type" => "object",
                        "properties" => [
                            "need_service_type"      => ["type" => "boolean", "default" => false],
                            "need_address"           => ["type" => "boolean", "default" => false],
                            "need_customer_info"     => ["type" => "boolean", "default" => false],
                            "ready_for_confirmation" => ["type" => "boolean", "default" => false],
                            "confirmed"              => ["type" => "boolean", "default" => false],
                            "cancelled"              => ["type" => "boolean", "default" => false]
                        ],
                        "required" => ["confirmed","cancelled"]
                    ]
                ],
                "required" => ["conversation","order","actions"]
            ]
        ]];

        // contenu utilisateur empaqueté
        $userEnvelope = json_encode([
            "from"     => $from,
            "state"    => $state,
            "menu"     => $menu,
            "catalogs" => $catalogs,
            "message"  => $message
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        // ✅ tool_choice au nouveau format (pas d’objet imbriqué "function": {...})
        $payload = [
            "model" => $this->model,
            "input" => [
                [
                    "role" => "system",
                    "content" => [
                        ["type" => "input_text", "text" => $system]
                    ]
                ],
                [
                    "role" => "user",
                    "content" => [
                        ["type" => "input_text", "text" => $userEnvelope]
                    ]
                ]
            ],
            "tools" => $tools,
            "tool_choice" => [
                "type" => "function",
                "name" => "submit_order"
            ],
            "max_output_tokens" => (int)($_ENV['AI_MAX_OUTPUT_TOKENS'] ?? 1200)
        ];

        $res = $this->http->request('POST', 'https://api.openai.com/v1/responses', [
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
            throw new \RuntimeException("OpenAI HTTP $status: ".$raw);
        }
        $data = json_decode($raw, true);

        $args = $this->extractToolArguments($data);
        if (!$args) throw new \RuntimeException("No tool arguments found in response: ".$raw);

        return $args;
    }

    private function extractToolArguments(array $data): ?array
    {
        // On rend l’extraction tolérante à plusieurs variantes du payload
        if (!isset($data['output']) || !is_array($data['output'])) return null;

        foreach ($data['output'] as $chunk) {
            // Variante A (fréquente) : { type: "tool_call", name: "...", arguments: "..." }
            if (($chunk['type'] ?? null) === 'tool_call' && ($chunk['name'] ?? null) === 'submit_order') {
                $args = $chunk['arguments'] ?? null;
                if (is_string($args)) {
                    $decoded = json_decode($args, true);
                    if (json_last_error() === JSON_ERROR_NONE) return $decoded;
                } elseif (is_array($args)) {
                    return $args;
                }
            }

            // Variante B (plus ancienne) : { type: "tool_call", tool_call: { type:"function", function:{ name, arguments } } }
            if (($chunk['type'] ?? null) === 'tool_call') {
                $tc = $chunk['tool_call'] ?? null;
                if (($tc['type'] ?? null) === 'function') {
                    $fn = $tc['function'] ?? null;
                    if (($fn['name'] ?? null) === 'submit_order') {
                        $args = $fn['arguments'] ?? null;
                        if (is_string($args)) {
                            $decoded = json_decode($args, true);
                            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
                        } elseif (is_array($args)) {
                            return $args;
                        }
                    }
                }
            }
        }
        return null;
    }

    public static function defaultMenu(): array
    {
        return [
            ["name" => "Pizza Margherita",   "price" => 45],
            ["name" => "Pizza 4 Fromages",   "price" => 65],
            ["name" => "Tacos Poulet",       "price" => 35],
            ["name" => "Coca 33cl",          "price" => 10],
            ["name" => "Eau 50cl",           "price" => 6],
        ];
    }
}
