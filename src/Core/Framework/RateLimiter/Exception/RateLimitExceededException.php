<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class RateLimitExceededException extends ShopwareHttpException
{
    private int $retryAfter;

    private int $now;

    public function __construct(int $retryAfter, ?\Throwable $e = null)
    {
        $this->retryAfter = $retryAfter;
        $this->now = time();

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
}
