<?php

declare(strict_types=1);

namespace App\Domain\Project\Enums;

/**
 * Type de service, tel que défini dans datacloud.yaml.
 */
enum ServiceType: string
{
    /** Reçoit du trafic HTTP et possède une adresse publique. */
    case Web = 'web';

    /** Tâche de fond (file d'attente, planificateur), sans adresse publique. */
    case Worker = 'worker';
}
