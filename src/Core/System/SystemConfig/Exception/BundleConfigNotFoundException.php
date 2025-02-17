<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class BundleConfigNotFoundException extends SystemConfigException
{
    public function __construct(
        string $configPath,
        string $bundleName
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0',
            'The constructor of BundleConfigNotFoundException will be removed in 6.8.0. Use SystemConfigException::bundleConfigNotFound Factory instead'
        );

        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::BUNDLE_CONFIG_NOT_FOUND,
            'Bundle configuration for path "{{ configPath }}" in bundle "{{ bundleName }}" not found.',
            ['configPath' => $configPath, 'bundleName' => $bundleName]
        );
    }
}
