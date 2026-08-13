<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRelease;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name' => 'وب‌اپلیکیشن', 'slug' => 'web-app'],
            ['name' => 'سایت', 'slug' => 'website'],
            ['name' => 'ابزار', 'slug' => 'tool'],
            ['name' => 'افزونه', 'slug' => 'plugin'],
        ])->map(function (array $category) {
            return ProductCategory::firstOrCreate(
                ['slug' => $category['slug']],
                $category,
            );
        });

        $products = Product::factory()->count(10)->create();

        $adminUserId = User::where('role', 'admin')->value('id') ?? 1;

        $products->each(function (Product $product, int $index) use ($categories, $adminUserId) {
            $product->categories()->sync($categories->random(2)->pluck('id'));

            ProductRelease::factory()
                ->count(fake()->numberBetween(1, 3))
                ->for($product)
                ->create([
                    'created_by' => $adminUserId,
                ]);
        });
    }
}
