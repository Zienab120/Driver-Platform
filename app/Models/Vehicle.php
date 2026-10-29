<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ['driver_profile_id','plate_numbers','plate_letters','model','color'];
    public function driverProfile() { return $this->belongsTo(DriverProfile::class); }
}
