<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimit;

#[Package('framework')]
class RateLimitExceededException extends ShopwareHttpException
{
    private readonly int $now;
    private readonly int $retryAfter;
    private readonly ?RateLimit $rateLimit;

    public function __construct(
        RateLimit|int $retryAfterOrRateLimit,
        ?\Throwable $e = null
    ) {
        $this->now = time();

        if ($retryAfterOrRateLimit instanceof RateLimit) {
            $this->rateLimit = $retryAfterOrRateLimit;
            $this->retryAfter = $retryAfterOrRateLimit->getRetryAfter()->getTimestamp();
        } else {
            $this->rateLimit = null;
            $this->retryAfter = $retryAfterOrRateLimit;
        }

        parent::__construct(
            'Too many requests, try again in {{ seconds }} seconds.',
            ['seconds' => $this->getWaitTime()],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__RATE_LIMIT_EXCEEDED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }

    public function getWaitTime(): int
    {
        return $this->retryAfter - $this->now;
    }

    public function getRateLimit(): ?RateLimit
    {
        return $this->rateLimit;
    }

    public function getHeaders(): array
    {
        $headers = parent::getHeaders();

        if ($this->rateLimit !== null) {
            $rateLimitHeaders = RateLimiter::getRateLimitHeaders($this->rateLimit);
            $headers = array_merge($headers, $rateLimitHeaders);
        } else {
            $headers['Retry-After'] = (string) $this->getWaitTime();
        }

        return $headers;
    }
}
