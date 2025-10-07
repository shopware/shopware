<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiterException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class RateLimitExceededException extends RateLimiterException
{
    private readonly int $now;

    public function __construct(
        private readonly int $retryAfter,
        ?\Throwable $e = null
    ) {
        $this->now = time();

        parent::__construct(
            Response::HTTP_TOO_MANY_REQUESTS,
            RateLimiterException::RATE_LIMIT_EXCEEDED,
            'Too many requests, try again in {{ seconds }} seconds.',
            ['seconds' => $this->getWaitTime()],
            $e
        );
    }

    public function getWaitTime(): int
    {
        return $this->retryAfter - $this->now;
    }
}
