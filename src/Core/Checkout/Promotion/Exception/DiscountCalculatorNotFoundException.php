<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class DiscountCalculatorNotFoundException extends ShopwareHttpException
{
    public function __construct(string $type)
    {
        parent::__construct('Promotion Discount Calculator "{{ type }}" has not been found!', ['type' => $type]);
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__DISCOUNT_CALCULATOR_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
