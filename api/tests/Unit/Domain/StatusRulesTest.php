<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Deployment\Enums\DeploymentStatus;
use App\Domain\Deployment\Enums\OperationStatus;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Enums\VariableOrigin;

/*
 * Petites règles métier portées par les états. Tests purs : ni base, ni framework.
 */

test('seuls les projets vivants comptent dans le quota de projets actifs', function (ProjectStatus $status, bool $counts) {
    expect($status->countsTowardsQuota())->toBe($counts);
})->with([
    'en attente de paiement' => [ProjectStatus::PendingPayment, true],
    'provisionnement' => [ProjectStatus::Provisioning, true],
    'actif' => [ProjectStatus::Active, true],
    'en échec' => [ProjectStatus::Failed, true],
    'arrêté' => [ProjectStatus::Stopped, false],
    'en suppression' => [ProjectStatus::Deleting, false],
]);

test('une opération est terminée seulement si elle a réussi ou échoué', function (OperationStatus $status, bool $finished) {
    expect($status->isFinished())->toBe($finished);
})->with([
    'en attente' => [OperationStatus::Pending, false],
    'en cours' => [OperationStatus::Running, false],
    'réussie' => [OperationStatus::Succeeded, true],
    'échouée' => [OperationStatus::Failed, true],
]);

test('un déploiement est terminé seulement s\'il est en ligne ou en échec', function (DeploymentStatus $status, bool $finished) {
    expect($status->isFinished())->toBe($finished);
})->with([
    'en attente' => [DeploymentStatus::Queued, false],
    'construction' => [DeploymentStatus::Building, false],
    'publication' => [DeploymentStatus::Releasing, false],
    'en ligne' => [DeploymentStatus::Live, true],
    'échec' => [DeploymentStatus::Failed, true],
]);

test('l\'argent est considéré comme reçu pour les commandes payées, créditées ou remboursées', function (OrderStatus $status, bool $paid) {
    expect($status->hasBeenPaid())->toBe($paid);
})->with([
    'en attente de paiement' => [OrderStatus::PendingPayment, false],
    'expirée' => [OrderStatus::Expired, false],
    'annulée' => [OrderStatus::Cancelled, false],
    'payée' => [OrderStatus::Paid, true],
    'créditée' => [OrderStatus::Credited, true],
    'remboursée' => [OrderStatus::Refunded, true],
]);

test('seule une valeur publique n\'est pas sensible', function (VariableOrigin $origin, bool $sensitive) {
    expect($origin->isSensitive())->toBe($sensitive);
})->with([
    'valeur publique' => [VariableOrigin::Plain, false],
    'secret fourni' => [VariableOrigin::Secret, true],
    'secret généré' => [VariableOrigin::Generated, true],
]);
