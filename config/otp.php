<?php

return [
    'length' => 6,
    'ttl' => 300,            // seconds
    'resend_after' => 60,    // seconds
    'max_attempts' => 5,
    'phone_max' => 3,
    'phone_window' => 15 * 60,
    'ip_max' => 10,
    'ip_window' => 60 * 60,

    // Fixed dev code. Also hard-guarded by !isProduction() in OtpService.
    // 'dev_code_enabled' => env('OTP_DEV_CODE_ENABLED', ! app()->isProduction()),
    'dev_code_enabled' => true,
    'dev_code' => '123456',
];
