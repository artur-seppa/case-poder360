<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'demo@example.com')->exists()) {
            return;
        }

        $demoUser = User::factory()->create([
            'name' => 'Usuário Demo',
            'email' => 'demo@example.com',
            'password' => bcrypt('password'),
        ]);

        // 20 tasks (> 15/page) so the demo user's task list shows real pagination (2 pages).
        Task::factory()->for($demoUser)->count(20)->create();

        $secondUser = User::factory()->create([
            'name' => 'Outro Usuário',
            'email' => 'outro@example.com',
            'password' => bcrypt('password'),
        ]);

        Task::factory()->for($secondUser)->count(3)->create();
    }
}
