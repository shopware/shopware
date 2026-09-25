<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\DeviceBoundSession;

use Shopware\Core\Framework\Log\Package;

/**
 * DBSC header names and the RFC 9651 structured field strings they carry.
 *
 * @internal
 */
#[Package('framework')]
final class DeviceBoundSessionHeader
{
    public const REGISTRATION = 'Secure-Session-Registration';
    public const CHALLENGE = 'Secure-Session-Challenge';
    public const RESPONSE = 'Secure-Session-Response';
    public const SESSION_ID = 'Sec-Secure-Session-Id';

    private function __construct()
    {
    }

    public static function serializeString(string $value): string
    {
        return '"' . addcslashes($value, '"\\') . '"';
    }

    /**
     * Reads the bare item of an sf-string or sf-token header and ignores its parameters.
     */
    public static function parseItem(?string $header): ?string
    {
        if ($header === null) {
            return null;
        }

        $header = trim($header);

        if (str_starts_with($header, '"')) {
            if (!preg_match('/^"((?:[^"\\\\]|\\\\["\\\\])*)"/', $header, $matches)) {
                return null;
            }

            $value = stripslashes($matches[1]);
        } else {
            $value = trim(explode(';', $header, 2)[0]);
        }

        return $value !== '' ? $value : null;
    }
}
