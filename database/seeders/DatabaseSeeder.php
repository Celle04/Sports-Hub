<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Sport;
use App\Models\Event;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Application;
use App\Models\Coach;
use App\Models\MedicalRecord;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
            'username' => 'admin',
            'password' => Hash::make('password'),
        ]);

        User::updateOrCreate(['email' => 'student@example.com'], [
            'name' => 'Juan Dela Cruz',
            'role' => 'Student',
            'username' => 'juan.delacruz',
            'student_id' => '2026-0001',
            'password' => Hash::make('password'),
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

        $student = User::where('email', 'student@example.com')->firstOrFail();
        $event = Event::where('title', 'Basketball Practice')->firstOrFail();

        $application = Application::updateOrCreate(['email' => 'maria.santos@example.com'], [
            'name' => 'Maria Santos', 'student_id' => '2026-0002', 'grade' => 'Grade 10', 'gender' => 'Female', 'sport' => 'Volleyball', 'sport_id' => Sport::where('name', 'Volleyball')->value('id'), 'medical_certificate_path' => 'application-documents/seed/medical.pdf', 'birth_certificate_path' => 'application-documents/seed/birth.pdf', 'parent_consent_path' => 'application-documents/seed/consent.pdf', 'status' => 'Pending',
        ]);

        foreach (['medical_certificate_path', 'birth_certificate_path', 'parent_consent_path'] as $document) {
            Storage::disk('private')->put($application->{$document}, "%PDF-1.4\nSportsHub sample document\n%%EOF\n");
        }

        foreach ([
            ['Coach Roberto Martinez', 'coach.martinez@snhhs.edu.ph', '+63 912 345 6789', 'Basketball'],
            ['Coach Linda Santos', 'coach.santos@snhhs.edu.ph', '+63 913 456 7890', 'Volleyball'],
        ] as [$name, $email, $phone, $specialty]) {
            Coach::updateOrCreate(['email' => $email], compact('name', 'email', 'phone', 'specialty'));
        }

        MedicalRecord::updateOrCreate(['user_id' => $student->id], [
            'athlete_id' => $student->id,
            'examination_date' => now()->subDays(20),
            'medical_status' => 'Cleared',
            'next_checkup_date' => now()->addDays(300),
            'findings' => 'Healthy and fit for school competitions.',
            'restrictions' => 'None',
            'clearance' => 'Cleared',
            'last_checkup' => now()->subDays(20),
            'notes' => 'Cleared for school sports activities.',
        ]);

        Attendance::updateOrCreate(['event_id' => $event->id, 'user_id' => $student->id], [
            'status' => 'Present', 'attended_on' => $event->starts_at->toDateString(),
        ]);

        foreach ([
            ['Basketball Tryouts This Friday', 'Basketball team tryouts will be held at the main court this Friday at 3:00 PM.'],
            ['Regional Sports Meet - June 2026', 'Athletes are encouraged to intensify their training sessions for the regional meet.'],
        ] as [$title, $body]) {
            Announcement::updateOrCreate(['title' => $title], [
                'body' => $body, 'published_at' => now()->toDateString(), 'status' => 'Published',
            ]);
        }
    }
}


