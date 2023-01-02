<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('merchant-services')]
class StoreLicenseDomainMissingException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Store license domain is missing');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_LICENSE_DOMAIN_IS_MISSING';
    }
}
