<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerAuthThrottledException extends ShopwareHttpException
{
    public function __construct(
        private readonly int $waitTime,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'Customer auth throttled for {{ seconds }} seconds.',
            ['seconds' => $this->waitTime],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_AUTH_THROTTLED';
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
