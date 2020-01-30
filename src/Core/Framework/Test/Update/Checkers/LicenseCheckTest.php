<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Checkers;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Update\Checkers\LicenseCheck;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class LicenseCheckTest extends TestCase
{
    public function testLicenseIsValidWithoutLicenseHost(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn(null);

        $licenseCheck = new LicenseCheck($systemConfig, $this->createMock(StoreClient::class));

        $validationResult = $licenseCheck->check(null)->jsonSerialize();

        static::assertTrue($validationResult['result']);
    }

    public function testIsValid(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn('licensehost.test');

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->method('isShopUpgradeable')->willReturn(true);

        $licenseCheck = new LicenseCheck($systemConfig, $storeClient);
        $validationResult = $licenseCheck->check(null)->jsonSerialize();

        static::assertTrue($validationResult['result']);
    }

    public function testIsInvalid(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn('licensehost.test');

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->method('isShopUpgradeable')->willReturn(false);

        $licenseCheck = new LicenseCheck($systemConfig, $storeClient);
        $validationResult = $licenseCheck->check(null)->jsonSerialize();

        static::assertFalse($validationResult['result']);
    }

    public function testSupports(): void
    {
        $licenseCheck = new LicenseCheck($this->createMock(SystemConfigService::class), $this->createMock(StoreClient::class));

        static::assertTrue($licenseCheck->supports('licensecheck'));
        static::assertFalse($licenseCheck->supports('phpversion'));
        static::assertFalse($licenseCheck->supports('mysqlversion'));
        static::assertFalse($licenseCheck->supports('writable'));
        static::assertFalse($licenseCheck->supports(''));
    }
}
