<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpChallenge extends Model
{
    protected $fillable = ['phone','role','code_hash','expires_at','last_sent_at','attempts','consumed_at','request_ip'];
    protected function casts(): array { return ['expires_at'=>'datetime','last_sent_at'=>'datetime','consumed_at'=>'datetime']; }
}
