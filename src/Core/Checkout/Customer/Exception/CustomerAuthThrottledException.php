<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class CustomerAuthThrottledException extends CustomerException
{
    public function __construct(
        private readonly int $waitTime,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::CUSTOMER_AUTH_THROTTLED,
            'Customer auth throttled for {{ seconds }} seconds.',
            ['seconds' => $this->waitTime],
            $e
        );
    }

    public function getWaitTime(): int
    {
        return $this->waitTime;
    }
}
