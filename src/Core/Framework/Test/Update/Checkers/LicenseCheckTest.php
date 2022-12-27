<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Checkers;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Update\Checkers\LicenseCheck;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class LicenseCheckTest extends TestCase
{
    public function testLicenseIsValidWithoutLicenseHost(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn(null);

        $licenseCheck = new LicenseCheck($systemConfig, $this->createMock(StoreClient::class));

        $validationResult = $licenseCheck->check()->jsonSerialize();

        static::assertTrue($validationResult['result']);
    }

    public function testIsValid(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn('licensehost.test');

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->method('isShopUpgradeable')->willReturn(true);

        $licenseCheck = new LicenseCheck($systemConfig, $storeClient);
        $validationResult = $licenseCheck->check()->jsonSerialize();

        static::assertTrue($validationResult['result']);
    }

    public function testIsInvalid(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn('licensehost.test');

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->method('isShopUpgradeable')->willReturn(false);

        $licenseCheck = new LicenseCheck($systemConfig, $storeClient);
        $validationResult = $licenseCheck->check()->jsonSerialize();

        static::assertFalse($validationResult['result']);
    }
}
