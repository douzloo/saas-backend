<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadNote;
use App\Models\LeadStage;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $tags = collect([
            ['name' => 'مهم', 'slug' => 'important', 'color' => '#EF4444'],
            ['name' => 'داغ', 'slug' => 'hot', 'color' => '#F97316'],
            ['name' => 'سرما', 'slug' => 'cold', 'color' => '#3B82F6'],
            ['name' => 'عمده', 'slug' => 'wholesale', 'color' => '#8B5CF6'],
            ['name' => 'استارتاپ', 'slug' => 'startup', 'color' => '#10B981'],
            ['name' => 'سازمانی', 'slug' => 'enterprise', 'color' => '#6366F1'],
        ]);

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['slug' => $tag['slug']], $tag);
        }

        $stages = [
            ['name' => 'جدید', 'key' => 'new', 'color' => '#3B82F6', 'sort_order' => 1],
            ['name' => 'در تماس', 'key' => 'contacted', 'color' => '#8B5CF6', 'sort_order' => 2],
            ['name' => 'واجد شرایط', 'key' => 'qualified', 'color' => '#0EA5E9', 'sort_order' => 3],
            ['name' => 'پیشنهاد', 'key' => 'proposal', 'color' => '#F59E0B', 'sort_order' => 4],
            ['name' => 'مذاکره', 'key' => 'negotiation', 'color' => '#F97316', 'sort_order' => 5],
            ['name' => 'برنده', 'key' => 'won', 'color' => '#10B981', 'sort_order' => 6],
            ['name' => 'از دست رفته', 'key' => 'lost', 'color' => '#EF4444', 'sort_order' => 7],
            ['name' => 'خاموش', 'key' => 'dormant', 'color' => '#6B7280', 'sort_order' => 8],
        ];

        foreach ($stages as $stage) {
            LeadStage::firstOrCreate(['key' => $stage['key']], $stage);
        }

        $staff = User::whereIn('role', ['admin', 'staff'])->get();

        if ($staff->isEmpty()) {
            $staff = User::factory()->count(2)->create(['role' => 'staff']);
        }

        $products = Product::all();

        $leads = Lead::factory()->count(30)->create([
            'assigned_to' => $staff->random()->id,
            'product_id' => $products->isNotEmpty() ? $products->random()->id : null,
        ]);

        $leads->each(function (Lead $lead) {
            $lead->tags()->sync(Tag::inRandomOrder()->limit(fake()->numberBetween(1, 3))->pluck('id'));

            if (fake()->boolean(60)) {
                LeadNote::create([
                    'lead_id' => $lead->id,
                    'user_id' => $lead->assigned_to,
                    'body' => fake()->paragraph(2),
                ]);
            }

            $activityCount = fake()->numberBetween(0, 4);
            for ($i = 0; $i < $activityCount; $i++) {
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $lead->assigned_to,
                    'type' => fake()->randomElement(['note', 'call', 'email', 'meeting']),
                    'subject' => fake()->optional()->sentence(4),
                    'description' => fake()->optional()->paragraph(),
                    'scheduled_at' => fake()->optional(0.3)->dateTimeBetween('-1 week', '+2 weeks'),
                ]);
            }
        });
    }
}
