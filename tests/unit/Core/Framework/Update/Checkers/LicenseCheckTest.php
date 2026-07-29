<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Checkers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Update\Checkers\LicenseCheck;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LicenseCheck::class)]
class LicenseCheckTest extends TestCase
{
    public function testLicenseIsValidWithoutLicenseHost(): void
    {
        $systemConfig = static::createStub(SystemConfigService::class);
        $systemConfig->method('get')->willReturn(null);

        $licenseCheck = new LicenseCheck($systemConfig, static::createStub(StoreClient::class));

        $validationResult = $licenseCheck->check()->jsonSerialize();

        static::assertTrue($validationResult['result']);
    }

    public function testIsValid(): void
    {
        $systemConfig = static::createStub(SystemConfigService::class);
        $systemConfig->method('get')->willReturn('licensehost.test');

        $storeClient = static::createStub(StoreClient::class);
        $storeClient->method('isShopUpgradeable')->willReturn(true);

        $licenseCheck = new LicenseCheck($systemConfig, $storeClient);
        $validationResult = $licenseCheck->check()->jsonSerialize();

        static::assertTrue($validationResult['result']);
    }

    public function testIsInvalid(): void
    {
        $systemConfig = static::createStub(SystemConfigService::class);
        $systemConfig->method('get')->willReturn('licensehost.test');

        $storeClient = static::createStub(StoreClient::class);
        $storeClient->method('isShopUpgradeable')->willReturn(false);

        $licenseCheck = new LicenseCheck($systemConfig, $storeClient);
        $validationResult = $licenseCheck->check()->jsonSerialize();

        static::assertFalse($validationResult['result']);
    }
}
