<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Showcase;
use App\Models\ShowcaseFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShowcaseFile>
 */
class ShowcaseFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = "<?php\n\necho 'Bonjour SamaCloud';\n";

        return [
            'showcase_id' => Showcase::factory(),
            'path' => 'src/'.Str::lower(Str::random(8)).'.php',
            'content' => $content,
            'size' => strlen($content),
        ];
    }
}
