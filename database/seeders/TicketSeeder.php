<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();

        if ($customers->isEmpty()) {
            $customers = User::factory()->count(6)->create();
        }

        $staff = User::whereIn('role', ['admin', 'staff'])->get();

        if ($staff->isEmpty()) {
            $staff = User::factory()->count(2)->create(['role' => 'staff']);
        }

        $products = Product::all();
        $licenses = License::all();

        $tickets = Ticket::factory()->count(20)->create();

        $tickets->each(function (Ticket $ticket, int $index) use ($customers, $staff, $products, $licenses) {
            $customer = $customers->random();

            $ticket->update([
                'user_id' => $customer->id,
                'product_id' => $products->isNotEmpty() && fake()->boolean(60) ? $products->random()->id : null,
                'license_id' => $licenses->isNotEmpty() && fake()->boolean(40) ? $licenses->random()->id : null,
                'assigned_to' => in_array($ticket->status, ['in_progress', 'resolved', 'closed'])
                    ? $staff->random()->id
                    : null,
            ]);

            $messageCount = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $messageCount; $i++) {
                $isStaff = $i % 2 === 1 && $ticket->assigned_to;

                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $isStaff ? $ticket->assigned_to : $customer->id,
                    'body' => fake()->paragraph(),
                    'is_staff_reply' => $isStaff,
                    'is_internal_note' => false,
                    'created_at' => now()->subHours(fake()->numberBetween(0, 120)),
                    'updated_at' => now()->subHours(fake()->numberBetween(0, 120)),
                ]);
            }
        });
    }
}
