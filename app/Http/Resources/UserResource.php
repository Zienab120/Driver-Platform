<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'full_name' => $this->full_name,
            'avatar_url' => $this->avatar_key
                ? Storage::disk(config('onboarding.uploads_disk'))->url($this->avatar_key)
                : null,
            'status' => $this->status->value,
        ];
    }
}
