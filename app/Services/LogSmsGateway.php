<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/** Development gateway. Replace with a real provider in production. */
final class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        // Never write OTP bodies to logs in production.
        Log::info('SMS', ['to' => substr($phone, 0, 6) . '****', 'body' => app()->isProduction() ? '[redacted]' : $message]);
    }
}
