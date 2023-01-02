<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class PluginCannotBeDeletedException extends ShopwareHttpException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            'Cannot delete plugin. Error: {{ error }}',
            ['error' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_CANNOT_BE_DELETED';
    }
}
