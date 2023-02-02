<?php declare(strict_types=1);

namespace Shopware\Administration\Controller\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class MissingShopUrlException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Failed to retrieve the shop url.');
    }

    public function getErrorCode(): string
    {
        return 'ADMINISTRATION__MISSING_SHOP_URL';
    }
}
