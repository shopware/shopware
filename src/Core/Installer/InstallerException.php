<?php

declare(strict_types=1);

namespace Shopware\Core\Installer;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class InstallerException extends HttpException
{
    final public const INVALID_REQUIREMENT_CHECK = 'INSTALLER__INVALID_REQUIREMENT_CHECK';
    final public const COUNTRY_NOT_FOUND = 'INSTALLER__COUNTRY_NOT_FOUND';
    final public const CURRENCY_NOT_FOUND = 'INSTALLER__CURRENCY_NOT_FOUND';
    final public const SHOP_CONFIGURATION_REQUIRED_VALUE_MISSING = 'INSTALLER__SHOP_CONFIGURATION_REQUIRED_VALUE_MISSING';

    public static function invalidRequirementCheck(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_REQUIREMENT_CHECK,
            $message,
        );
    }

    public static function countryNotFound(string $iso): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::COUNTRY_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'country', 'field' => 'iso3', 'value' => $iso]
        );
    }

    public static function currencyNotFound(string $currencyName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CURRENCY_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'currency', 'field' => 'iso_code', 'value' => $currencyName]
        );
    }

    public static function shopConfigurationRequiredValueMissing(string $valueName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SHOP_CONFIGURATION_REQUIRED_VALUE_MISSING,
            'Shop configuration value "{{ valueName }}" is missing or invalid.',
            ['valueName' => $valueName]
        );
    }
}
