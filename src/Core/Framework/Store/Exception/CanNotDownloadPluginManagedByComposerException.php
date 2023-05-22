<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.6.0 - Will be removed as it is unused
 */
#[Package('merchant-services')]
class CanNotDownloadPluginManagedByComposerException extends ShopwareHttpException
{
    public function __construct(string $reason)
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0'));
        parent::__construct(
            'Can not download plugin. Please contact your system administrator. Error: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.6.0.0'));

        return 'FRAMEWORK__STORE_CANNOT_DOWNLOAD_PLUGIN_MANAGED_BY_SHOPWARE';
    }
}
