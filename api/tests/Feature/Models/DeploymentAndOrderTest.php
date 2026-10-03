<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Deployment\Enums\DeploymentStatus;
use App\Domain\Deployment\Enums\OperationStatus;
use App\Domain\Shared\Enums\ActorType;
use App\Models\Deployment;
use App\Models\Operation;
use App\Models\Order;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('un déploiement est relié à son projet, à son opération de suivi et à son auteur', function () {
    $project = Project::factory()->create();
    $deployment = Deployment::factory()->for($project)->byAi()->create()->fresh();

    expect($deployment?->project->is($project))->toBeTrue()
        ->and($deployment?->operation->project_id)->toBe($project->id)
        ->and($deployment?->operation->deployment?->is($deployment))->toBeTrue()
        ->and($deployment?->user_id)->toBe($project->user_id)
        ->and($deployment?->actor)->toBe(ActorType::Ai)
        ->and($deployment?->status)->toBe(DeploymentStatus::Queued);
});

test('une opération ne peut suivre qu\'un seul déploiement', function () {
    $deployment = Deployment::factory()->create();

    expect(fn () => DB::transaction(
        fn () => Deployment::factory()->create(['operation_id' => $deployment->operation_id]),
    ))->toThrow(UniqueConstraintViolationException::class);
});

test('l\'avancement d\'une opération reste entre 0 et 100', function (int $invalidProgress) {
    expect(fn () => DB::transaction(
        fn () => Operation::factory()->create(['progress' => $invalidProgress]),
    ))->toThrow(QueryException::class);
})->with([
    'au-dessus de 100' => [101],
    'négatif' => [-1],
]);

test('une nouvelle opération démarre à 0 % en attente', function () {
    $operation = Operation::factory()->create()->fresh();

    expect($operation?->progress)->toBe(0)
        ->and($operation?->status)->toBe(OperationStatus::Pending)
        ->and($operation?->started_at)->toBeNull();
});

test('une commande payée garde son montant, son devis et sa période', function () {
    $order = Order::factory()->paid()->create(['amount_fcfa' => 7500])->fresh();

    expect($order?->status)->toBe(OrderStatus::Paid)
        ->and($order?->amount_fcfa)->toBe(7500)
        ->and($order?->quote)->toHaveKey('total_fcfa')
        ->and($order?->period_starts_at?->diffInDays($order->period_ends_at))->toEqual(30.0);
});

test('la base refuse un montant négatif', function () {
    expect(fn () => DB::transaction(fn () => Order::factory()->create(['amount_fcfa' => -1])))
        ->toThrow(QueryException::class);
});

test('une même référence de paiement ne peut pas servir à deux commandes', function () {
    Order::factory()->paid()->create(['provider_reference' => 'ref-123']);

    expect(fn () => DB::transaction(
        fn () => Order::factory()->paid()->create(['provider_reference' => 'ref-123']),
    ))->toThrow(UniqueConstraintViolationException::class);
});
