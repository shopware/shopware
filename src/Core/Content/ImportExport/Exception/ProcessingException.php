<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @package system-settings
 */
class ProcessingException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_PROCESSING_EXCEPTION';
    }
}
