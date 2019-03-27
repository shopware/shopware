<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class BundleConfigNotFoundException extends ShopwareHttpException
{
    public function __construct(string $bundleName)
    {
        parent::__construct(
            'Could not find "Resources/config.xml" for bundle "{{ bundle }}".',
            ['bundle' => $bundleName]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__BUNDLE_CONFIG_NOT_FOUND';
    }
}
