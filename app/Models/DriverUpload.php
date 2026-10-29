<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverUpload extends Model
{
    protected $fillable = ['user_id','file_key','type','path','mime_type','size_bytes','attached_at'];
    protected function casts(): array { return ['attached_at'=>'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
}
