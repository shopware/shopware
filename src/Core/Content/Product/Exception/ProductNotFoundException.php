<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class ProductNotFoundException extends ShopwareHttpException
{
    public function __construct(string $productId)
    {
        parent::__construct(
            'Product for id {{ productId }} not found.',
            ['productId' => $productId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
