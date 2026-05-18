<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiterException;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class RateLimitExceededException extends RateLimiterException
{
    private readonly int $now;

    public function __construct(
        private readonly int $retryAfter,
        ?\Throwable $e = null
    ) {
        // @TODO clock-non-di: NativeClock fallback in exception; review
        $this->now = (new NativeClock())->now()->getTimestamp();

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
