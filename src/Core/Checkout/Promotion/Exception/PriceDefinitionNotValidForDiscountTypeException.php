<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class PriceDefinitionNotValidForDiscountTypeException extends ShopwareHttpException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__INVALID_PRICE_DEFINITION_FOR_DISCOUNT_TYPE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
