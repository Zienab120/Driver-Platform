<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $fillable = ['user_id','national_id_encrypted','national_id_hash','birth_date','gender','district_id','submitted_at','rejection_reason'];
    protected $hidden = ['national_id_encrypted','national_id_hash'];
    protected function casts(): array { return ['birth_date'=>'date','submitted_at'=>'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function vehicle() { return $this->hasOne(Vehicle::class); }
    public function documents() { return $this->hasMany(DriverDocument::class); }
}
