<?php declare(strict_types=1);

namespace Shopware\Core\Framework\JWT;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class JWTException extends HttpException
{
    private const INVALID_JWT = 'UTIL__INVALID_JWT';
    private const MISSING_DOMAIN = 'UTIL__MISSING_DOMAIN';
    private const INVALID_DOMAIN = 'UTIL__INVALID_DOMAIN';
    private const INVALID_TYPE = 'UTIL__INVALID_TYPE';

    public static function invalidJwt(string $reason, ?\Exception $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_JWT,
            (!str_contains($reason, 'Invalid JWT: ') ? 'Invalid JWT: ' : '') . '{{ message }}',
            ['message' => $reason],
            $e
        );
    }

    public static function missingDomain(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_DOMAIN,
            'Missing domain in system configuration'
        );
    }

    public static function invalidDomain(string $domain): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOMAIN,
            'Invalid domain in system configuration: "{{ domain }}"',
            ['domain' => $domain]
        );
    }

    public static function invalidType(string $expected, string $actual): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_TYPE,
            \sprintf('Expected collection element of type %s got %s', $expected, $actual)
        );
    }
}
