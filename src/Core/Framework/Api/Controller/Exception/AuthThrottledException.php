<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimit;

#[Package('framework')]
class AuthThrottledException extends ShopwareHttpException
{
    private readonly int $waitTime;
    private readonly ?RateLimit $rateLimit;

    public function __construct(
        RateLimit|int $rateLimit,
        ?\Throwable $e = null
    ) {
        if ($rateLimit instanceof RateLimit) {
            $this->rateLimit = $rateLimit;
            $this->waitTime = $rateLimit->getRetryAfter()->getTimestamp() - time();
        } else {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', '');
            $this->rateLimit = null;
            $this->waitTime = $rateLimit;
        }

        parent::__construct(
            'Auth throttled for {{ seconds }} seconds.',
            ['seconds' => $this->getWaitTime()],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__AUTH_THROTTLED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }

    public function getWaitTime(): int
    {
        return $this->waitTime;
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
