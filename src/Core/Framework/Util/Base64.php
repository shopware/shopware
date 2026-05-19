<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Exception\Base64DecodingException;

/**
 * The standard base64 alphabet contains + and / which are not URL safe.
 * Base64url encoding replaces + with - and / with _ and removes padding characters, as described in
 * RFC 4648, Section 5 https://datatracker.ietf.org/doc/html/rfc4648#section-5.
 */
#[Package('framework')]
class Base64
{
    /**
     * Encodes a string in base64url format as described in RFC 4648, Section 5.
     */
    public static function urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodes a string in base64url format as described in RFC 4648, Section 5.
     *
     * @throws Base64DecodingException
     */
    public static function urlDecode(string $data): string
    {
        $decoded = base64_decode(
            strtr($data, '-_', '+/'),
            true,
        );

        if ($decoded === false) {
            throw UtilException::base64DecodingFailed();
        }

        return $decoded;
    }
}
