<?php

namespace Database\Seeders;

use App\Models\Download;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DownloadSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::where('is_downloadable', true)->get();

        if ($products->isEmpty()) {
            $products = Product::factory()->count(3)->create(['is_downloadable' => true]);
        }

        foreach ($products as $product) {
            $release = $product->releases()->published()->first();

            Download::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'version' => $product->version,
                    'platform' => 'universal',
                ],
                [
                    'product_release_id' => $release?->id,
                    'filename' => Str::slug($product->name).'-'.$product->version.'.zip',
                    'original_filename' => $product->name.'.zip',
                    'mime_type' => 'application/zip',
                    'file_size' => $product->file_size ?? 1024 * 1024 * 50,
                    'file_hash' => fake()->sha256(),
                    'download_url' => '/storage/downloads/'.Str::slug($product->name).'.zip',
                    'changelog' => fake()->optional()->paragraph(),
                    'status' => 'available',
                    'is_active' => true,
                    'is_stable' => true,
                    'download_count' => fake()->numberBetween(0, 2000),
                ],
            );
        }
    }
}
