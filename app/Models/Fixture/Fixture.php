<?php

namespace App\Models\Fixture;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fixture extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'goals' => 'array',
        'goalkeeper_saves' => 'array',
        'match_statistics' => 'array',
    ];

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}