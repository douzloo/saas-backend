<?php

namespace Database\Factories;

use App\Models\Download;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Download>
 */
class DownloadFactory extends Factory
{
    protected $model = Download::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_release_id' => null,
            'version' => '1.0.0',
            'filename' => Str::slug(implode(' ', [fake()->word(), fake()->word()])).'-1.0.0.zip',
            'original_filename' => implode(' ', [fake()->word(), fake()->word()]).'.zip',
            'mime_type' => 'application/zip',
            'file_size' => fake()->numberBetween(10, 500) * 1024 * 1024,
            'file_hash' => fake()->sha256(),
            'download_url' => fake()->url(),
            'changelog' => fake()->optional()->paragraph(),
            'platform' => fake()->randomElement(['windows', 'macos', 'linux', 'web', 'source', 'universal']),
            'status' => 'available',
            'is_active' => true,
            'is_stable' => true,
            'download_count' => fake()->numberBetween(0, 1000),
        ];
    }
}
