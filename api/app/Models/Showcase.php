<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Showcase\Enums\ImportStatus;
use App\Domain\Showcase\GitHubRepository;
use Carbon\CarbonImmutable;
use Database\Factories\ShowcaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Projet présenté dans la vitrine, avec une copie de son code.
 * À ne pas confondre avec Project, qui est un projet hébergé et payé.
 *
 * @property string $id
 * @property int $user_id
 * @property string $title
 * @property string $description
 * @property string $repository_owner
 * @property string $repository_name
 * @property string|null $branch
 * @property string|null $demo_url
 * @property ImportStatus $import_status
 * @property string|null $import_error
 * @property CarbonImmutable|null $imported_at
 * @property int $files_count
 * @property int $total_bytes
 * @property bool $is_truncated
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $owner
 * @property-read Collection<int, Technology> $technologies
 * @property-read int|null $stargazers_count
 * @property-read int|null $comments_count
 */
#[Fillable([
    'user_id', 'title', 'description', 'repository_owner', 'repository_name', 'branch', 'demo_url',
    'import_status', 'import_error', 'imported_at', 'files_count', 'total_bytes', 'is_truncated',
])]
class Showcase extends Model
{
    /** @use HasFactory<ShowcaseFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Valeurs par défaut d'un nouveau projet (identiques à celles de la base).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'files_count' => 0,
        'total_bytes' => 0,
        'is_truncated' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'import_status' => ImportStatus::class,
            'imported_at' => 'datetime',
            'files_count' => 'integer',
            'total_bytes' => 'integer',
            'is_truncated' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsToMany<Technology, $this>
     */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class);
    }

    /**
     * @return HasMany<ShowcaseFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ShowcaseFile::class);
    }

    /**
     * Comptes qui ont donné une étoile à ce projet.
     *
     * @return BelongsToMany<User, $this>
     */
    public function stargazers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'showcase_stars')->withPivot('created_at');
    }

    /**
     * @return HasMany<ShowcaseComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ShowcaseComment::class);
    }

    public function repository(): GitHubRepository
    {
        return GitHubRepository::fromParts($this->repository_owner, $this->repository_name);
    }
}
