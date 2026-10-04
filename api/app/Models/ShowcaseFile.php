<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShowcaseFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fichier de code d'un projet de la vitrine (copie SamaCloud ; GitHub n'est jamais modifié).
 *
 * @property string $id
 * @property string $showcase_id
 * @property string $path
 * @property string $content
 * @property int $size
 */
#[Fillable(['showcase_id', 'path', 'content', 'size'])]
class ShowcaseFile extends Model
{
    /** @use HasFactory<ShowcaseFileFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Showcase, $this>
     */
    public function showcase(): BelongsTo
    {
        return $this->belongsTo(Showcase::class);
    }
}
