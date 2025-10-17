<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class RateLimiterException extends HttpException
{
    public const RATE_LIMIT_EXCEEDED = 'FRAMEWORK__RATE_LIMIT_EXCEEDED';
    public const FACTORY_NOT_FOUND = 'FRAMEWORK__RATE_LIMITER_FACTORY_NOT_FOUND';

    public static function limitExceeded(int $retryAfter, ?\Throwable $e = null): self
    {
        return new RateLimitExceededException($retryAfter, $e);
    }

    public static function factoryNotFound(string $route): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FACTORY_NOT_FOUND,
            'Rate limiter factory for route "{{ route }}" not found.',
            ['route' => $route]
        );
    }
}
