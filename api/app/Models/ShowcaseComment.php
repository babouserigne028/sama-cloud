<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ShowcaseCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Commentaire laissé sur un projet de la vitrine.
 *
 * @property string $id
 * @property string $showcase_id
 * @property int $user_id
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property-read User $author
 * @property-read Showcase $showcase
 */
#[Fillable(['showcase_id', 'user_id', 'body'])]
class ShowcaseComment extends Model
{
    /** @use HasFactory<ShowcaseCommentFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return BelongsTo<Showcase, $this>
     */
    public function showcase(): BelongsTo
    {
        return $this->belongsTo(Showcase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
