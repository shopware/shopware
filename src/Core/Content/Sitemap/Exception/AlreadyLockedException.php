<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class AlreadyLockedException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'CONTENT__SITEMAP_ALREADY_LOCKED';
    }
}
