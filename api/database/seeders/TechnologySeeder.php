<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Technology;
use Illuminate\Database\Seeder;

/**
 * Liste de départ des technologies. Elle peut être relancée sans risque :
 * une technologie déjà présente est simplement mise à jour.
 */
class TechnologySeeder extends Seeder
{
    /**
     * slug => nom affiché.
     *
     * @var array<string, string>
     */
    private const array TECHNOLOGIES = [
        // Langages
        'php' => 'PHP', 'javascript' => 'JavaScript', 'typescript' => 'TypeScript', 'python' => 'Python',
        'java' => 'Java', 'kotlin' => 'Kotlin', 'dart' => 'Dart', 'go' => 'Go', 'csharp' => 'C#',
        'swift' => 'Swift', 'rust' => 'Rust', 'sql' => 'SQL',
        // Back-end
        'laravel' => 'Laravel', 'symfony' => 'Symfony', 'nodejs' => 'Node.js', 'express' => 'Express',
        'nestjs' => 'NestJS', 'django' => 'Django', 'fastapi' => 'FastAPI', 'flask' => 'Flask',
        'spring-boot' => 'Spring Boot', 'dotnet' => '.NET',
        // Front-end
        'angular' => 'Angular', 'react' => 'React', 'vue' => 'Vue.js', 'nextjs' => 'Next.js',
        'svelte' => 'Svelte', 'tailwindcss' => 'Tailwind CSS', 'html-css' => 'HTML / CSS',
        // Mobile
        'flutter' => 'Flutter', 'react-native' => 'React Native', 'android' => 'Android', 'ios' => 'iOS',
        // Données
        'postgresql' => 'PostgreSQL', 'mysql' => 'MySQL', 'mongodb' => 'MongoDB', 'redis' => 'Redis',
        'firebase' => 'Firebase',
        // Infrastructure
        'docker' => 'Docker', 'linux' => 'Linux', 'git' => 'Git', 'ci-cd' => 'CI / CD', 'nginx' => 'Nginx',
        // Paiement local
        'wave' => 'Wave', 'orange-money' => 'Orange Money', 'mobile-money' => 'Mobile Money',
        // Métiers
        'securite' => 'Sécurité', 'ui-ux' => 'UI / UX', 'ia' => 'Intelligence artificielle', 'data' => 'Data',
    ];

    public function run(): void
    {
        foreach (self::TECHNOLOGIES as $slug => $name) {
            Technology::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}
