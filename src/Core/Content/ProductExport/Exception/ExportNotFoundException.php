<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class ExportNotFoundException extends ShopwareHttpException
{
    public function __construct(?string $id = null)
    {
        $message = $id
            ? 'Product export with ID {{ id }} not found'
            : 'No product exports found';

        parent::__construct($message, ['id' => $id]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_NOT_FOUND';
    }
}
