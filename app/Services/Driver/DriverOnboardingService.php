<?php

namespace App\Services\Driver;

use App\Enums\UserStatus;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Models\DriverUpload;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DriverOnboardingService
{
    public function step1(User $user, array $data): array
    {
        $hash=hash_hmac('sha256',$data['national_id'],(string)config('app.key'));
        if (DriverProfile::where('national_id_hash',$hash)->where('user_id','!=',$user->id)->exists()) throw new HttpException(409,'NATIONAL_ID_ALREADY_REGISTERED');
        return DB::transaction(function () use ($user,$data,$hash) {
            $profile=DriverProfile::firstOrNew(['user_id'=>$user->id]);
            $profile->fill(['national_id_encrypted'=>Crypt::encryptString($data['national_id']),'national_id_hash'=>$hash,'birth_date'=>$data['birth_date'],'gender'=>$data['gender']]);
            $profile->save(); $user->forceFill(['full_name'=>$data['full_name'],'avatar_key'=>$data['avatar_key']])->save();
            $this->attachUpload($user,$profile,$data['avatar_key'],'avatar');
            return ['step'=>1,'completed'=>true];
        });
    }
    public function step2(User $user, array $data): array
    {
        $profile=$user->driverProfile; if (!$profile) throw new HttpException(409,'STEP_1_REQUIRED');
        $numbers=$data['plate_numbers']; $letters=preg_replace('/\s+/u','',$data['plate_letters']);
        if (Vehicle::where('plate_numbers',$numbers)->where('plate_letters',$letters)->where('driver_profile_id','!=',$profile->id)->exists()) throw new HttpException(409,'PLATE_ALREADY_REGISTERED');
        return DB::transaction(function () use ($profile,$user,$data,$numbers,$letters) {
            $vehicle=Vehicle::updateOrCreate(['driver_profile_id'=>$profile->id],['plate_numbers'=>$numbers,'plate_letters'=>$letters,'model'=>$data['model'],'color'=>$data['color']]);
            foreach ($data['documents'] as $type=>$key) $this->attachUpload($user,$profile,$key,$type);
            return ['step'=>2,'completed'=>true];
        });
    }
    public function step3(User $user, array $data): array
    {
        $profile=$user->driverProfile; if (!$profile) throw new HttpException(409,'STEP_1_REQUIRED');
        return DB::transaction(function () use ($user,$profile,$data) { foreach ($data['documents'] as $type=>$key) $this->attachUpload($user,$profile,$key,$type); return ['step'=>3,'completed'=>true]; });
    }
    public function submit(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $profile=$user->driverProfile()->with(['vehicle','documents'])->first();
            if (!$profile || !$user->full_name || !$user->avatar_key || !$profile->national_id_hash || !$profile->birth_date || !$profile->gender) throw new HttpException(409,'ONBOARDING_INCOMPLETE');
            if (!$profile->vehicle) throw new HttpException(409,'ONBOARDING_INCOMPLETE');
            $required=['avatar','vehicle_front','vehicle_back','vehicle_interior','national_id_front','national_id_back','driving_license','vehicle_license','criminal_record'];
            $saved=$profile->documents->pluck('type')->unique()->all(); if (array_diff($required,$saved)) throw new HttpException(409,'ONBOARDING_INCOMPLETE');
            if ($user->status->value==='pending_review') throw new HttpException(409,'APPLICATION_ALREADY_SUBMITTED');
            if (!in_array($user->status->value,['needs_profile','rejected'],true)) throw new HttpException(409,'APPLICATION_NOT_ELIGIBLE');
            $profile->forceFill(['submitted_at'=>now(),'rejection_reason'=>null])->save(); $user->forceFill(['status'=>UserStatus::PendingReview])->save();
            return ['status'=>'pending_review','submitted_at'=>$profile->submitted_at?->toISOString()];
        });
    }
    private function attachUpload(User $user, DriverProfile $profile, string $key, string $type): void
    {
        $upload=DriverUpload::where('user_id',$user->id)->where('file_key',$key)->where('type',$type)->whereNull('attached_at')->first();
        if (!$upload) {
            // Idempotent save: permit the same already-attached file for this profile/type.
            $existing=DriverDocument::where('driver_profile_id',$profile->id)->where('type',$type)->where('file_key',$key)->first();
            if ($existing) return;
            throw new HttpException(422,'FILE_INVALID');
        }
        DriverDocument::updateOrCreate(['driver_profile_id'=>$profile->id,'type'=>$type],['file_key'=>$upload->file_key,'mime_type'=>$upload->mime_type,'size_bytes'=>$upload->size_bytes]);
        $upload->forceFill(['attached_at'=>now()])->save();
    }
}
