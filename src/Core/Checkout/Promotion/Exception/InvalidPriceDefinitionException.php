<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidPriceDefinitionException extends ShopwareHttpException
{
    public function __construct(LineItem $discount)
    {
        if ($discount->getReferencedId() === null) {
            parent::__construct(
                'Invalid price definition for automated promotion "{{ label }}"',
                ['label' => $discount->getLabel()]
            );

            return;
        }

        parent::__construct(
            'Invalid price definition for promotion line item with code "{{ code }}"',
            ['code' => $discount->getReferencedId()]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__INVALID_PROMOTION_PRICE_DEFINITION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
