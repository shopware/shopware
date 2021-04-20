<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class BundleConfigNotFoundException extends ShopwareHttpException
{
    public function __construct(string $configPath, string $bundleName, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Could not find "{{ configPath }}" for bundle "{{ bundle }}".',
            [
                'configPath' => $configPath,
                'bundle' => $bundleName,
            ],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__BUNDLE_CONFIG_NOT_FOUND';
    }
}
