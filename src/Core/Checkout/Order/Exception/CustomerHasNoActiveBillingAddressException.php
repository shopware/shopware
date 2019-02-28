<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CustomerHasNoActiveBillingAddressException extends ShopwareHttpException
{
    protected $code = 'CUSTOMER-NO-ACTIVE-BILLING-ADDRESS';

    public function __construct(string $customerId, $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Customer %s has no active billing address id', $customerId);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
