<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Content\ProductStream\Exception\EmptyProductStreamException;
use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('inventory')]
class ProductStreamException extends HttpException
{
    public static function productStreamNotFound(string $id): ShopwareHttpException
    {
        return new EntityNotFoundException('product_stream', $id);
    }

    public static function noFilters(string $id): ShopwareHttpException
    {
        return new NoFilterException($id);
    }

    public static function emptyProductStream(string $id): EmptyProductStreamException
    {
        return new EmptyProductStreamException($id);
    }
}
