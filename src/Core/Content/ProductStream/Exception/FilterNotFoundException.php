<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class FilterNotFoundException extends ShopwareHttpException
{
    public function __construct($type)
    {
        parent::__construct('Filter for type {{ type}} not found', ['type' => $type]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_STREAM_FILTER_NOT_FOUND';
    }
}
