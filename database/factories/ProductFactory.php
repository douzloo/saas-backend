<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = implode(' ', [
            fake()->unique()->word(),
            fake()->unique()->word(),
            fake()->unique()->word(),
        ]);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraphs(3, true),
            'short_description' => fake()->sentence(),
            'version' => '1.0.0',
            'sku' => 'PRD-'.strtoupper(Str::random(8)),
            'price' => fake()->randomElement([0, 149000, 249000, 490000, 990000, 1490000]),
            'trial_days' => fake()->randomElement([0, 0, 7, 14, 30]),
            'type' => fake()->randomElement(['standard', 'pro', 'enterprise']),
            'status' => 'active',
            'is_downloadable' => fake()->boolean(70),
            'requires_activation' => fake()->boolean(80),
            'activation_strategy' => fake()->randomElement([null, 'domain', 'machine', 'hybrid']),
            'max_domains' => fake()->numberBetween(1, 10),
            'default_max_activations' => fake()->numberBetween(1, 5),
            'features' => fake()->randomElements([
                'پشتیبانی ۲۴/۷',
                'آپدیت رایگان',
                'نصب آسان',
                'گزارش‌گیری پیشرفته',
                'امنیت بالا',
                'چند زبانه',
                'پشتیبان‌گیری خودکار',
                'یکپارچه‌سازی API',
            ], fake()->numberBetween(3, 6)),
            'requirements' => fake()->randomElements([
                'PHP 8.1+',
                'MySQL 5.7+',
                'Windows 10+',
                'Linux / macOS',
                'حداقل 4GB RAM',
                'فایرفاکس / کروم',
            ], 3),
            'file_size' => fake()->numberBetween(10, 500) * 1024 * 1024,
            'file_hash' => fake()->sha256(),
            'sort_order' => fake()->numberBetween(1, 100),
            'latest_release_version' => '1.0.0',
            'category' => fake()->randomElement(['سایت', 'وب‌اپلیکیشن', 'ابزار', 'افزونه', null]),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
            'requires_activation' => false,
            'activation_strategy' => 'none',
        ]);
    }
}
