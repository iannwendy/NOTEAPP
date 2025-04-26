<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PasswordResetOtp extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'otp_code',
        'expires_at',
        'is_used'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    /**
     * Generate a new OTP for the specified email.
     *
     * @param string $email
     * @return string The generated OTP
     */
    public static function generateOtp(string $email): string
    {
        // Invalidate any existing OTPs for this email
        self::where('email', $email)->update(['is_used' => true]);

        // Generate a 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create a new OTP record
        self::create([
            'email' => $email,
            'otp_code' => $otp,
            'expires_at' => now()->addMinutes(15), // OTP expires in 15 minutes
            'is_used' => false
        ]);

        return $otp;
    }

    /**
     * Validate the OTP for the given email.
     *
     * @param string $email
     * @param string $otp
     * @return bool
     */
    public static function validateOtp(string $email, string $otp): bool
    {
        $otpRecord = self::where('email', $email)
            ->where('otp_code', $otp)
            ->where('expires_at', '>=', now())
            ->where('is_used', false)
            ->first();

        if ($otpRecord) {
            $otpRecord->is_used = true;
            $otpRecord->save();
            return true;
        }

        return false;
    }
}
