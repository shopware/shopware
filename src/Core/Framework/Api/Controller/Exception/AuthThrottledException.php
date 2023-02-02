<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class AuthThrottledException extends ShopwareHttpException
{
    private int $waitTime;

    public function __construct(int $waitTime, ?\Throwable $e = null)
    {
        $this->waitTime = $waitTime;

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
}
