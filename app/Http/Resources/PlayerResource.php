<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Explicit public allowlist: new database columns are never exposed automatically.
        $data = $this->resource->only(['id', 'team_id', 'name', 'photo', 'jersey_number', 'is_active']);
        if ($this->resource->relationLoaded('team')) {
            $data['team'] = $this->team?->only(['id', 'name', 'logo', 'founded_year', 'description']);
        }
        if ($request->user('sanctum')?->is_admin) {
            $data += $this->resource->only(['school_origin', 'birth_place_date', 'qr_code', 'created_at', 'updated_at']);
        }
        return $data;
    }
}
