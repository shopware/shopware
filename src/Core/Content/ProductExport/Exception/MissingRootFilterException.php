<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class MissingRootFilterException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Missing root filter ');
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_EMPTY';
    }
}
