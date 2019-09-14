<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class EmptyExportException extends ShopwareHttpException
{
    public function __construct(?string $id = null)
    {
        if (empty($id)) {
            parent::__construct('No products for export found');
        } else {
            parent::__construct('No products for export with ID {{ id }} found', ['id' => $id]);
        }
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_EMPTY';
    }
}
