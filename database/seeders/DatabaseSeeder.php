<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Sport;
use App\Models\Event;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin User',
            'role' => 'Administrator',
            'password' => 'password',
        ]);

        User::updateOrCreate(['email' => 'student@example.com'], [
            'name' => 'Juan Dela Cruz',
            'role' => 'Student',
            'password' => 'password',
        ]);

        foreach ([
            ['Basketball', 'Team Sport', '5 vs 5 indoor court game'],
            ['Volleyball', 'Team Sport', '6v6 net sport'],
            ['Track and Field', 'Individual', 'Running and field events'],
            ['Badminton', 'Individual', 'Singles and doubles racket sport'],
            ['Table Tennis', 'Individual', 'Competitive indoor racket sport'],
            ['Chess', 'Individual', 'Strategic board game'],
        ] as [$name, $classification, $description]) {
            Sport::updateOrCreate(['name' => $name], [
                'classification' => $classification,
                'description' => $description,
            ]);
        }

        User::where('email', 'student@example.com')->update([
            'sport_id' => Sport::where('name', 'Basketball')->value('id'),
        ]);

        foreach ([
            ['Regional Sports Meet', 'Main Court', '2026-08-30 15:00:00', '2026-08-30 17:00:00'],
            ['Basketball Practice', 'Main Court', '2026-09-02 15:00:00', '2026-09-02 17:00:00'],
            ['Volleyball Training', 'Gymnasium', '2026-09-05 15:00:00', '2026-09-05 17:00:00'],
        ] as [$title, $venue, $startsAt, $endsAt]) {
            Event::updateOrCreate(['title' => $title], [
                'venue' => $venue,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'Scheduled',
                'sport_id' => str_contains($title, 'Volleyball')
                    ? Sport::where('name', 'Volleyball')->value('id')
                    : Sport::where('name', 'Basketball')->value('id'),
            ]);
        }
    }
}
