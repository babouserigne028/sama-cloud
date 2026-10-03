<?php

declare(strict_types=1);

namespace App\Domain\Account\Enums;

use App\Domain\Shared\Enums\ActorType;

/**
 * Type d'un jeton d'accès. C'est lui qui dit si une action vient d'un humain ou de l'IA.
 */
enum TokenKind: string
{
    /** Jeton du back-office, obtenu à la connexion : c'est un humain qui agit. */
    case Session = 'session';

    /** Jeton donné au serveur MCP : c'est l'IA qui agit au nom du développeur. */
    case Agent = 'agent_ia';

    /**
     * Auteur à inscrire dans le journal d'audit. On se fie au jeton,
     * jamais à une information envoyée par le client.
     */
    public function actor(): ActorType
    {
        return match ($this) {
            self::Session => ActorType::Human,
            self::Agent => ActorType::Ai,
        };
    }
}
