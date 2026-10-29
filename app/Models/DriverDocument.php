<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;

class DriverDocument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => DocumentType::class, 'reviewed_at' => 'datetime'];
    }
}
