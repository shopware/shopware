<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('system-settings')]
class BundleConfigNotFoundException extends ShopwareHttpException
{
    public function __construct(
        string $configPath,
        string $bundleName
    ) {
        parent::__construct(
            'Could not find "{{ configPath }}" for bundle "{{ bundle }}".',
            [
                'configPath' => $configPath,
                'bundle' => $bundleName,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__BUNDLE_CONFIG_NOT_FOUND';
    }
}
