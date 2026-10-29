<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Step1Request;
use App\Http\Requests\Step2Request;
use App\Http\Requests\Step3Request;
use App\Http\Resources\DriverStatusResource;
use App\Services\DriverOnboardingService;
use Illuminate\Http\Request;

class DriverOnboardingController extends Controller
{
    public function __construct(private readonly DriverOnboardingService $onboarding) {}

    public function step1(Step1Request $request): DriverStatusResource
    {
        $this->onboarding->saveStep1($request->user(), $request->validated());

        return $this->progress($request);
    }

    public function step2(Step2Request $request): DriverStatusResource
    {
        $this->onboarding->saveStep2($request->user(), $request->validated());

        return $this->progress($request);
    }

    public function step3(Step3Request $request): DriverStatusResource
    {
        $this->onboarding->saveStep3($request->user(), $request->validated());

        return $this->progress($request);
    }

    public function submit(Request $request): DriverStatusResource
    {
        $this->onboarding->submit($request->user());

        return $this->progress($request);
    }

    public function status(Request $request): DriverStatusResource
    {
        return $this->progress($request);
    }

    private function progress(Request $request): DriverStatusResource
    {
        return DriverStatusResource::make($request->user()->fresh(['driver.documents']));
    }
}
