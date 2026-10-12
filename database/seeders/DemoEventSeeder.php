<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoEventSeeder extends Seeder
{
    public function run(): void
    {
        $event = Event::updateOrCreate(
            ['slug' => 'devs-meet-nairobi-2026'],
            [
                'title' => 'Devs Meet Nairobi 2026',
                'description' => "A full day of talks and workshops on modern web engineering.\n\nThree tracks, two stages, and plenty of hallway track.",
                'venue' => 'Kenyatta International Convention Centre',
                'address' => 'Harambee Ave',
                'city' => 'Nairobi',
                'starts_at' => Carbon::now()->addDays(30)->setTime(9, 0),
                'ends_at' => Carbon::now()->addDays(30)->setTime(18, 0),
                'registration_deadline' => Carbon::now()->addDays(28)->setTime(23, 59),
                'status' => EventStatus::Published,
                'capacity' => 500,
                'created_by' => 1,
            ],
        );

        $this->ticketTypes($event);

        $music = Event::updateOrCreate(
            ['slug' => 'sunset-sessions-2026'],
            [
                'title' => 'Sunset Sessions 2026',
                'description' => 'An evening of live music on the rooftop.',
                'venue' => 'The Rooftop',
                'address' => 'Westlands Road',
                'city' => 'Nairobi',
                'starts_at' => Carbon::now()->addDays(45)->setTime(16, 0),
                'ends_at' => Carbon::now()->addDays(45)->setTime(22, 0),
                'registration_deadline' => Carbon::now()->addDays(43)->setTime(23, 59),
                'status' => EventStatus::Published,
                'created_by' => 1,
            ],
        );

        $this->ticketTypes($music);
    }

    private function ticketTypes(Event $event): void
    {
        TicketType::updateOrCreate(
            ['event_id' => $event->id, 'name' => 'Early Bird'],
            [
                'description' => 'Limited allocation at the lowest price.',
                'price' => 2500,
                'currency' => 'KES',
                'requires_payment' => true,
                'quantity_total' => 100,
                'quantity_sold' => 0,
                'quantity_per_order_max' => 5,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        TicketType::updateOrCreate(
            ['event_id' => $event->id, 'name' => 'Standard'],
            [
                'description' => 'Full access to all tracks.',
                'price' => 4500,
                'currency' => 'KES',
                'requires_payment' => true,
                'quantity_total' => 300,
                'quantity_sold' => 0,
                'quantity_per_order_max' => 10,
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        TicketType::updateOrCreate(
            ['event_id' => $event->id, 'name' => 'VIP'],
            [
                'description' => 'Front-row seating, speaker dinner and swag.',
                'price' => 12000,
                'currency' => 'KES',
                'requires_payment' => true,
                'quantity_total' => 50,
                'quantity_sold' => 0,
                'quantity_per_order_max' => 2,
                'is_active' => true,
                'sort_order' => 3,
            ],
        );

        TicketType::updateOrCreate(
            ['event_id' => $event->id, 'name' => 'Community (Free)'],
            [
                'description' => 'Free community allocation — no payment required.',
                'price' => 0,
                'currency' => 'KES',
                'requires_payment' => false,
                'quantity_total' => 50,
                'quantity_sold' => 0,
                'quantity_per_order_max' => 2,
                'is_active' => true,
                'sort_order' => 4,
            ],
        );
    }
}