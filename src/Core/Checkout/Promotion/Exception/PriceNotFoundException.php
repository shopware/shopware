<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 *
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use PromotionException::priceNotFound() instead
 */
#[Package('checkout')]
class PriceNotFoundException extends PromotionException
{
    public function __construct(LineItem $item)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            parent::PRICE_NOT_FOUND_FOR_ITEM,
            'No calculated price found for item {{ itemId }}',
            ['itemId' => $item->getId()]
        );
    }
}
