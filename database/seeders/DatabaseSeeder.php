<?php

namespace Database\Seeders;

use App\Models\Player\Player;
use App\Models\Team\Team;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $teamA = Team::create([
            'name' => 'Garuda Merah FC',
            'logo' => null,
            'founded_year' => 1998,
            'description' => 'Klub papan atas yang dikenal dengan permainan menyerang cepat.'
        ]);

        $teamB = Team::create([
            'name' => 'Bintang Nusantara',
            'logo' => null,
            'founded_year' => 2005,
            'description' => 'Klub dengan pertahanan terkuat dan lini tengah yang kreatif.'
        ]);

        $teamC = Team::create([
            'name' => 'Satria Muda United',
            'logo' => null,
            'founded_year' => 2012,
            'description' => 'Klub penuh talenta muda berbakat dari berbagai penjuru negeri.'
        ]);

        Player::create([
            'team_id' => $teamA->id,
            'name' => 'Budi Santoso',
            'position' => 'Forward',
            'jersey_number' => 10,
            'birth_date' => '1999-04-12',
            'height' => 178,
            'weight' => 72,
            'overall_rating' => 8.75,
            'is_active' => true,
        ]);

        Player::create([
            'team_id' => $teamA->id,
            'name' => 'Andi Pratama',
            'position' => 'Midfielder',
            'jersey_number' => 8,
            'birth_date' => '2001-08-22',
            'height' => 175,
            'weight' => 68,
            'overall_rating' => 8.10,
            'is_active' => true,
        ]);

        Player::create([
            'team_id' => $teamB->id,
            'name' => 'Rizky Ramadhan',
            'position' => 'Defender',
            'jersey_number' => 4,
            'birth_date' => '1997-01-15',
            'height' => 184,
            'weight' => 78,
            'overall_rating' => 7.90,
            'is_active' => true,
        ]);

        Player::create([
            'team_id' => $teamB->id,
            'name' => 'Dimas Setiawan',
            'position' => 'Goalkeeper',
            'jersey_number' => 1,
            'birth_date' => '1995-11-30',
            'height' => 188,
            'weight' => 82,
            'overall_rating' => 8.40,
            'is_active' => true,
        ]);

        Player::create([
            'team_id' => $teamC->id,
            'name' => 'Fajar Hidayat',
            'position' => 'Forward',
            'jersey_number' => 7,
            'birth_date' => '2002-05-05',
            'height' => 172,
            'weight' => 66,
            'overall_rating' => 8.25,
            'is_active' => true,
        ]);

        Player::create([
            'team_id' => $teamC->id,
            'name' => 'Eko Prasetyo',
            'position' => 'Midfielder',
            'jersey_number' => 14,
            'birth_date' => '2000-09-19',
            'height' => 176,
            'weight' => 70,
            'overall_rating' => 7.85,
            'is_active' => true,
        ]);
    }
}