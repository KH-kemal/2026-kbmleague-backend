<?php

namespace App\Models\Team;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $guarded = ['id'];

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }
}