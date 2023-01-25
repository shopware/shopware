<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('business-ops')]
class NoFilterException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct('Product stream with ID {{ id }} has no filters', ['id' => $id]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_STREAM_MISSING_FILTER';
    }
}
