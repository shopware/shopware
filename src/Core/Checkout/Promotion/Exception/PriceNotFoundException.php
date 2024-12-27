<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class PriceNotFoundException extends ShopwareHttpException
{
    public function __construct(LineItem $item)
    {
        parent::__construct('No calculated price found for item ' . $item->getId());
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__PRICE_NOT_FOUND_FOR_ITEM';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
