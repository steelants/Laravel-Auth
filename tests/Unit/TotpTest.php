<?php

namespace SteelAnts\LaravelAuth\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SteelAnts\LaravelAuth\Support\Totp;

class TotpTest extends TestCase
{
    // Base32 of the ASCII secret "12345678901234567890" used by RFC 6238 test vectors.
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public static function rfc6238Vectors(): array
    {
        return [
            [59, '94287082'],
            [1111111109, '07081804'],
            [1111111111, '14050471'],
            [1234567890, '89005924'],
            [2000000000, '69279037'],
        ];
    }

    #[DataProvider('rfc6238Vectors')]
    public function test_it_generates_rfc6238_codes(int $timestamp, string $expected)
    {
        $this->assertSame($expected, Totp::generateCode(self::RFC_SECRET, $timestamp, 30, 8));
    }

    public function test_it_generates_six_digit_codes_by_default()
    {
        $this->assertSame('287082', Totp::generateCode(self::RFC_SECRET, 59));
    }

    public function test_secret_decoding_ignores_case_and_invalid_characters()
    {
        $this->assertSame(
            Totp::generateCode(self::RFC_SECRET, 59),
            Totp::generateCode(strtolower(self::RFC_SECRET) . '===', 59)
        );
    }

    public function test_it_generates_base32_secrets()
    {
        $secret = Totp::generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertNotSame($secret, Totp::generateSecret());
        $this->assertSame(16, strlen(Totp::generateSecret(10)));
    }

    public function test_it_verifies_current_code()
    {
        $secret = Totp::generateSecret();

        $this->assertTrue(Totp::verify($secret, Totp::generateCode($secret)));
    }

    public function test_it_accepts_codes_within_window()
    {
        $secret = Totp::generateSecret();

        $this->assertTrue(Totp::verify($secret, Totp::generateCode($secret, time() - 30)));
        $this->assertTrue(Totp::verify($secret, Totp::generateCode($secret, time() + 30)));
    }

    public function test_it_rejects_codes_outside_window()
    {
        $secret = Totp::generateSecret();

        $this->assertFalse(Totp::verify($secret, Totp::generateCode($secret, time() - 120)));
        $this->assertFalse(Totp::verify($secret, Totp::generateCode($secret, time() - 120), 0));
    }

    public function test_it_rejects_invalid_code()
    {
        $secret = Totp::generateSecret();
        $code = Totp::generateCode($secret);
        $wrong = str_pad((string) (((int) $code + 500000) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->assertFalse(Totp::verify($secret, $wrong));
        $this->assertFalse(Totp::verify($secret, ''));
    }

    public function test_it_builds_otpauth_url()
    {
        $this->assertSame(
            'otpauth://totp/My%20App%3Ajohn%40example.com?secret=ABC&issuer=My%20App&digits=6&period=30',
            Totp::otpauthUrl('john@example.com', 'My App', 'ABC')
        );
    }

    public function test_it_builds_png_qr_code_data_uri()
    {
        $uri = Totp::buildTotpQr(Totp::otpauthUrl('john@example.com', 'App', Totp::generateSecret()));

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertNotFalse(base64_decode(substr($uri, strlen('data:image/png;base64,')), true));
    }
}
