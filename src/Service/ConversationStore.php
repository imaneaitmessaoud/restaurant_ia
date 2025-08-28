<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;

class ConversationStore
{
    public function __construct(private CacheInterface $cache) {}

    private function key(string $from): string { return 'conv_'.md5($from); }

    public function get(string $from): array
    {
        return $this->cache->get($this->key($from), fn() => [
            'step'          => 'idle', // idle|await_items|await_service|await_address|await_confirm
            'items'         => [],     // [['name'=>'Margherita','qty'=>2,'price'=>45], ...]
            'type_service'  => null,   // sur_place|emporter|livraison
            'address'       => null,
            'name'          => null,
            'phone'         => null,
            'total'         => 0.0,
            'created_at'    => time(),
        ]);
    }

    public function set(string $from, array $state): void
    {
        $this->cache->delete($this->key($from));
        $this->cache->get($this->key($from), fn() => $state);
    }

    public function reset(string $from): void
    {
        $this->cache->delete($this->key($from));
    }
    private function seenKey(string $sid): string { return 'seen_'.$sid; }

 public function seen(string $sid): bool
 {
    return (bool)$this->cache->get($this->seenKey($sid), fn() => false);
 }

 public function markSeen(string $sid): void
 {
    $this->cache->delete($this->seenKey($sid));
    $this->cache->get($this->seenKey($sid), fn() => true);
 }

}
