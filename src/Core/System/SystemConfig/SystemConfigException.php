<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Exception\BundleConfigNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class SystemConfigException extends HttpException
{
    public const SYSTEM_MANAGED_SYSTEM_CONFIG = 'SYSTEM__MANAGED_SYSTEM_CONFIG_CANNOT_BE_CHANGED';
    public const INVALID_DOMAIN = 'SYSTEM__INVALID_DOMAIN';
    public const CONFIG_NOT_FOUND = 'SYSTEM__SCOPE_NOT_FOUND';
    public const BUNDLE_CONFIG_NOT_FOUND = 'SYSTEM__BUNDLE_CONFIG_NOT_FOUND';

    public static function systemConfigKeyIsManagedBySystems(string $configKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SYSTEM_MANAGED_SYSTEM_CONFIG,
            'The system configuration key "{{ configKey }}" cannot be changed, as it is managed by the Shopware yaml file configuration system provided by Symfony.',
            [
                'configKey' => $configKey,
            ],
        );
    }

    public static function invalidDomain(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOMAIN,
            'Invalid domain',
        );
    }

    public static function configurationNotFound(string $scope): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CONFIG_NOT_FOUND,
            'Configuration for scope "{{ scope }}" not found.',
            ['scope' => $scope]
        );
    }

    public static function bundleConfigNotFound(string $configPath, string $bundleName): BundleConfigNotFoundException
    {
        // Exception is intended to be catched, therefore we keep separate exception class
        return new BundleConfigNotFoundException($configPath, $bundleName);
    }
}
