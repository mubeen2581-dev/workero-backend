<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Company;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;

class CommunicationSeeder extends Seeder
{
    /**
     * Seed communication data (conversations and messages)
     */
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Please run DatabaseSeeder first.');
            return;
        }

        $users = User::where('company_id', $company->id)->get();
        if ($users->count() < 2) {
            $this->command->warn('Need at least 2 users to create conversations. Please seed users first.');
            return;
        }

        $admin = $users->where('role', 'admin')->first();
        $manager = $users->where('role', 'manager')->first() ?? $users->where('role', '!=', 'admin')->first();
        $technician = $users->where('role', 'technician')->first() ?? $users->where('role', '!=', 'admin')->where('role', '!=', $manager->role)->first();

        if (!$admin || !$manager) {
            $this->command->warn('Need admin and manager users to create conversations.');
            return;
        }

        $conversationsCreated = 0;
        $messagesCreated = 0;

        // Create conversation between admin and manager
        $conversation1 = Conversation::create([
            'id' => Str::uuid(),
            'company_id' => $company->id,
            'title' => $manager->first_name . ' ' . $manager->last_name,
            'type' => 'internal',
            'participant_id' => $manager->id,
            'participant_type' => 'App\Models\User',
            'last_message_at' => Carbon::now()->subMinutes(5),
            'unread_count' => 2,
        ]);

        // Add messages to conversation 1
        Message::create([
            'id' => Str::uuid(),
            'company_id' => $company->id,
            'conversation_id' => $conversation1->id,
            'sender_id' => $manager->id,
            'sender_type' => 'App\Models\User',
            'receiver_id' => $admin->id,
            'receiver_type' => 'App\Models\User',
            'type' => 'text',
            'content' => 'Hi! I need to discuss the upcoming project schedule.',
            'is_read' => false,
        ]);

        Message::create([
            'id' => Str::uuid(),
            'company_id' => $company->id,
            'conversation_id' => $conversation1->id,
            'sender_id' => $admin->id,
            'sender_type' => 'App\Models\User',
            'receiver_id' => $manager->id,
            'receiver_type' => 'App\Models\User',
            'type' => 'text',
            'content' => 'Sure, let\'s schedule a meeting. What time works for you?',
            'is_read' => false,
        ]);

        Message::create([
            'id' => Str::uuid(),
            'company_id' => $company->id,
            'conversation_id' => $conversation1->id,
            'sender_id' => $manager->id,
            'sender_type' => 'App\Models\User',
            'receiver_id' => $admin->id,
            'receiver_type' => 'App\Models\User',
            'type' => 'text',
            'content' => 'How about tomorrow at 2 PM?',
            'is_read' => false,
        ]);

        $conversationsCreated++;
        $messagesCreated += 3;

        // Create conversation between admin and technician (if exists)
        if ($technician) {
            $conversation2 = Conversation::create([
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'title' => $technician->first_name . ' ' . $technician->last_name,
                'type' => 'internal',
                'participant_id' => $technician->id,
                'participant_type' => 'App\Models\User',
                'last_message_at' => Carbon::now()->subHours(2),
                'unread_count' => 0,
            ]);

            Message::create([
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'conversation_id' => $conversation2->id,
                'sender_id' => $admin->id,
                'sender_type' => 'App\Models\User',
                'receiver_id' => $technician->id,
                'receiver_type' => 'App\Models\User',
                'type' => 'text',
                'content' => 'Please update the job status when you complete the installation.',
                'is_read' => true,
            ]);

            Message::create([
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'conversation_id' => $conversation2->id,
                'sender_id' => $technician->id,
                'sender_type' => 'App\Models\User',
                'receiver_id' => $admin->id,
                'receiver_type' => 'App\Models\User',
                'type' => 'text',
                'content' => 'Will do! I\'ll update it as soon as I finish.',
                'is_read' => true,
            ]);

            $conversationsCreated++;
            $messagesCreated += 2;
        }

        // Create conversation between manager and technician (if exists)
        if ($technician && $manager) {
            $conversation3 = Conversation::create([
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'title' => $technician->first_name . ' ' . $technician->last_name,
                'type' => 'internal',
                'participant_id' => $technician->id,
                'participant_type' => 'App\Models\User',
                'last_message_at' => Carbon::now()->subDays(1),
                'unread_count' => 1,
            ]);

            Message::create([
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'conversation_id' => $conversation3->id,
                'sender_id' => $technician->id,
                'sender_type' => 'App\Models\User',
                'receiver_id' => $manager->id,
                'receiver_type' => 'App\Models\User',
                'type' => 'text',
                'content' => 'I need more materials for the job tomorrow. Can you check inventory?',
                'is_read' => false,
            ]);

            $conversationsCreated++;
            $messagesCreated++;
        }

        $this->command->info("Created {$conversationsCreated} conversations successfully!");
        $this->command->info("Created {$messagesCreated} messages successfully!");
    }
}

