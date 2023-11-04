<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class UnknownPromotionDiscountTypeException extends ShopwareHttpException
{
    public function __construct(PromotionDiscountEntity $discount)
    {
        parent::__construct(
            'Unknown promotion discount type detected: {{ type }}',
            ['type' => $discount->getType()]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__UNKNOWN_PROMOTION_DISCOUNT_TYPE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
