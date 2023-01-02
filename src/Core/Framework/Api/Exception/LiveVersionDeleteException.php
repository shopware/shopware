<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class LiveVersionDeleteException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Live version can not be deleted. Delete entity instead.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__LIVE_VERSION_DELETE';
    }
}
