<?php

namespace App\Support;

/**
 * The six-digit codes of a two-step sign-in (RFC 6238, as Google Authenticator and the other
 * authenticator apps read them): a secret shared once, and a code that changes every 30 seconds.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A new secret: 160 random bits, written in the 32 letters an authenticator app accepts. */
    public static function secret(): string
    {
        $secret = '';
        foreach (unpack('C*', random_bytes(32)) as $byte) {
            $secret .= self::ALPHABET[$byte & 31];
        }

        return $secret;
    }

    public static function code(string $secret, ?int $time = null): string
    {
        $counter = intdiv($time ?? time(), 30);
        $hash = hash_hmac('sha1', pack('J', $counter), self::decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $number = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % 1000000;

        return str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    /** The code of now, or of the 30 seconds just before or after it (clocks are never exactly the same). */
    public static function verify(string $secret, string $code, ?int $time = null): bool
    {
        $code = preg_replace('/\D+/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $time ??= time();
        foreach ([0, -30, 30] as $shift) {
            if (hash_equals(self::code($secret, $time + $shift), $code)) {
                return true;
            }
        }

        return false;
    }

    /** What the QR code holds: an authenticator app reads the secret, the business and the user from it. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    private static function decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret))) as $letter) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $letter)), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8) as $byte) {
            $bytes .= chr(bindec($byte));
        }

        return $bytes;
    }
}
