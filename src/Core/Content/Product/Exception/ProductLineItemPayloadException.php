<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class ProductLineItemPayloadException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'The payload of the product line item could not be processed.'
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__INVALID_PAYLOAD';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
