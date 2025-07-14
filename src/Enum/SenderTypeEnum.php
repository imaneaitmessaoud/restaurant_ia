<?php

namespace App\Enum;

enum SenderTypeEnum: string
{
    case CLIENT = 'client';
    case BOT = 'bot';
    case HUMAIN = 'humain';
    
    public function getLabel(): string
    {
        return match($this) {
            self::CLIENT => 'Client',
            self::BOT => 'IA Bot',
            self::HUMAIN => 'Agent Humain',
        };
    }
    
    public function isAutomated(): bool
    {
        return $this === self::BOT;
    }
    
    public function isHuman(): bool
    {
        return in_array($this, [self::CLIENT, self::HUMAIN]);
    }
}