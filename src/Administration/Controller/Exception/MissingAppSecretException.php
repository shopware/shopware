<?php declare(strict_types=1);

namespace Shopware\Administration\Controller\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('administration')]
class MissingAppSecretException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Failed to retrieve app secret.');
    }

    public function getErrorCode(): string
    {
        return 'ADMINISTRATION__MISSING_APP_SECRET';
    }
}
