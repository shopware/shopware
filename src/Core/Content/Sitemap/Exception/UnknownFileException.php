<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @package sales-channel
 */
class UnknownFileException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'CONTENT__SITEMAP_UNKNOWN_FILE';
    }
}
