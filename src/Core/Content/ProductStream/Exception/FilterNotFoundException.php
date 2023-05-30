<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('business-ops')]
class FilterNotFoundException extends ShopwareHttpException
{
    public function __construct(string $type)
    {
        parent::__construct('Filter for type {{ type}} not found', ['type' => $type]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_STREAM_FILTER_NOT_FOUND';
    }
}
