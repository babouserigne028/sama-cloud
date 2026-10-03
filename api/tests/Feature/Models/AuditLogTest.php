<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\ActorType;
use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Le journal d'audit est en « ajout seul » : c'est PostgreSQL qui le garantit.
 */

test('une ligne du journal est enregistrée et relue avec ses vrais types', function () {
    $log = AuditLog::factory()->byAi()->create(['metadata' => ['nom' => 'mon-blog']])->fresh();

    expect($log?->actor)->toBe(ActorType::Ai)
        ->and($log?->action)->toBe('projet.cree')
        ->and($log?->metadata)->toBe(['nom' => 'mon-blog'])
        ->and($log?->created_at)->not->toBeNull();
});

test('une ligne du journal ne peut pas être modifiée, même par une requête directe', function () {
    $log = AuditLog::factory()->create();

    expect(fn () => DB::transaction(fn () => $log->update(['action' => 'projet.efface'])))
        ->toThrow(QueryException::class, 'ajout seul');

    expect(fn () => DB::transaction(
        fn () => DB::table('audit_logs')->where('id', $log->id)->update(['actor' => 'humain']),
    ))->toThrow(QueryException::class, 'ajout seul');

    expect($log->fresh()?->action)->toBe('projet.cree');
});

test('une ligne du journal ne peut pas être supprimée', function () {
    $log = AuditLog::factory()->create();

    expect(fn () => DB::transaction(fn () => $log->delete()))
        ->toThrow(QueryException::class, 'ajout seul');

    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->truncate()))
        ->toThrow(QueryException::class, 'ajout seul');

    expect(AuditLog::query()->count())->toBe(1);
});

test('une action de la plateforme peut être journalisée sans compte', function () {
    $log = AuditLog::factory()->create(['user_id' => null, 'actor' => ActorType::System]);

    expect($log->fresh()?->user)->toBeNull();
});
