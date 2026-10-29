<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Domain error rendered as {"error": {"code": ..., "message": ..., ...extra}}. */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $response = response()->json([
            'error' => ['code' => $this->errorCode, 'message' => $this->getMessage(), ...$this->extra],
        ], $this->status);

        if (isset($this->extra['retry_after_seconds'])) {
            $response->header('Retry-After', (string) $this->extra['retry_after_seconds']);
        }

        return $response;
    }

    public static function roleMismatch(): self { return new self('ROLE_MISMATCH', 'This phone number is registered under a different role.', 409); }
    public static function accountSuspended(): self { return new self('ACCOUNT_SUSPENDED', 'Your account is suspended. Please contact support.', 403); }
    public static function otpRateLimit(int $retryAfter): self { return new self('OTP_RATE_LIMIT', 'Too many code requests. Try again later.', 429, ['retry_after_seconds' => $retryAfter]); }
    public static function otpInvalid(int $attemptsLeft): self { return new self('OTP_INVALID', 'The code is incorrect.', 400, ['attempts_left' => $attemptsLeft]); }
    public static function otpExpired(): self { return new self('OTP_EXPIRED', 'The code has expired. Request a new one.', 400); }
    public static function otpTooManyAttempts(): self { return new self('OTP_TOO_MANY_ATTEMPTS', 'Too many incorrect attempts. Request a new code.', 429); }
    public static function refreshInvalid(): self { return new self('REFRESH_INVALID', 'Refresh token is invalid.', 401); }
    public static function forbidden(): self { return new self('FORBIDDEN', 'You do not have access to this resource.', 403); }
    public static function driverNotApproved(): self { return new self('DRIVER_NOT_APPROVED', 'Your driver account is not approved.', 403); }
    public static function nationalIdTaken(): self { return new self('NATIONAL_ID_ALREADY_REGISTERED', 'This national ID is already registered.', 409); }
    public static function plateTaken(): self { return new self('PLATE_ALREADY_REGISTERED', 'This plate is already registered.', 409); }
    public static function applicationLocked(): self { return new self('APPLICATION_LOCKED', 'The application can no longer be edited or submitted.', 409); }
    public static function applicationIncomplete(array $steps, array $documents): self
    {
        return new self('APPLICATION_INCOMPLETE', 'The application is incomplete.', 422, ['missing_steps' => $steps, 'missing_documents' => $documents]);
    }
}
