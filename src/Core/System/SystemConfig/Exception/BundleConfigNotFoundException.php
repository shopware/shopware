<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

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
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::BUNDLE_CONFIG_NOT_FOUND,
            'Bundle configuration for path "{{ configPath }}" in bundle "{{ bundleName }}" not found.',
            ['configPath' => $configPath, 'bundleName' => $bundleName]
        );
    }
}
