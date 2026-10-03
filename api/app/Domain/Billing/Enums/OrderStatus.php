<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

/**
 * État d'une commande. Le paiement passe toujours avant la création des ressources.
 */
enum OrderStatus: string
{
    /** Lien de paiement émis, en attente du client. */
    case PendingPayment = 'en_attente_paiement';

    /** Paiement confirmé par le webhook du fournisseur. */
    case Paid = 'payee';

    /** Lien expiré sans paiement. */
    case Expired = 'expiree';

    /** Annulée avant paiement. */
    case Cancelled = 'annulee';

    /** Payée trop tard sans capacité disponible : le montant devient un crédit. */
    case Credited = 'creditee';

    /** Montant rendu au client. */
    case Refunded = 'remboursee';

    /**
     * L'argent a-t-il été reçu pour cette commande ?
     */
    public function hasBeenPaid(): bool
    {
        return match ($this) {
            self::Paid, self::Credited, self::Refunded => true,
            self::PendingPayment, self::Expired, self::Cancelled => false,
        };
    }
}
