<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle\Registration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\Registration\HandshakeFactory;
use Shopware\Core\Framework\App\Lifecycle\Registration\PrivateHandshake;
use Shopware\Core\Framework\App\Lifecycle\Registration\StoreHandshake;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class HandshakeFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testManifestWithSecretProducesAPrivateHandshake(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../Manifest/_fixtures/minimal/manifest.xml');

        $shopUrl = 'test.shop.com';

        $factory = new HandshakeFactory(
            $shopUrl,
            $this->getContainer()->get(ShopIdProvider::class),
            $this->getContainer()->get(StoreClient::class)
        );

        $handshake = $factory->create($manifest);

        static::assertInstanceOf(PrivateHandshake::class, $handshake);
    }

    public function testThrowsAppRegistrationExceptionIfAppUrlChangeWasDetected(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../Manifest/_fixtures/minimal/manifest.xml');

        $shopUrl = 'test.shop.com';

        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => Uuid::randomHex(),
        ]);

        $factory = new HandshakeFactory(
            $shopUrl,
            $this->getContainer()->get(ShopIdProvider::class),
            $this->getContainer()->get(StoreClient::class)
        );

        static::expectException(AppRegistrationException::class);
        $factory->create($manifest);
    }

    public function testManifestWithoutSecretProducesAStoreHandshake(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../Manifest/_fixtures/private/manifest.xml');

        $shopUrl = 'test.shop.com';

        $factory = new HandshakeFactory(
            $shopUrl,
            $this->getContainer()->get(ShopIdProvider::class),
            $this->getContainer()->get(StoreClient::class)
        );

        $handshake = $factory->create($manifest);

        static::assertInstanceOf(StoreHandshake::class, $handshake);
    }
}
