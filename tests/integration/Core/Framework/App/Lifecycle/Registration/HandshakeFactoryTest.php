<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Registration;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppHandshakeInterface;
use Shopware\Core\Framework\App\Lifecycle\Registration\HandshakeFactory;
use Shopware\Core\Framework\App\Lifecycle\Registration\PrivateHandshake;
use Shopware\Core\Framework\App\Lifecycle\Registration\StoreHandshake;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class HandshakeFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testThrowsAppRegistrationExceptionIfShopIdFingerprintsHaveChanged(): void
    {
        $context = Context::createDefaultContext();
        $this->getContainer()->get('app.repository')->create([[
            'name' => 'SwagApp',
            'path' => __DIR__ . '/../../Manifest/_fixtures/minimal',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'testtoken',
            'appSecret' => 'test',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'testkey',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
        ]], $context);

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../Manifest/_fixtures/minimal/manifest.xml');

        $shopUrl = 'test.shop.com';

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2, (array) ShopId::v2(Uuid::randomHex(), [
            AppUrl::IDENTIFIER => 'https://test.com',
        ]));

        static::getContainer()->get(ShopIdProvider::class)->reset();

        $factory = new HandshakeFactory(
            $shopUrl,
            static::getContainer()->get(ShopIdProvider::class),
            static::getContainer()->get(StoreClient::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('test-app');

        static::expectException(AppRegistrationException::class);
        $factory->create($manifest, $app);
    }

    #[After]
    public function deleteShopId(): void
    {
        static::getContainer()->get(ShopIdProvider::class)->deleteShopId();
    }

    /**
     * @param class-string<AppHandshakeInterface> $expectedHandshake
     */
    #[DataProvider('manifestProvider')]
    public function testManifestWithoutShopSecret(Manifest $manifest, string $expectedHandshake): void
    {
        $shopUrl = 'test.shop.com';

        $factory = new HandshakeFactory(
            $shopUrl,
            static::getContainer()->get(ShopIdProvider::class),
            static::getContainer()->get(StoreClient::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('test-app');

        $handshake = $factory->create($manifest, $app);

        static::assertInstanceOf($expectedHandshake, $handshake);

        static::assertNull($this->getSecretProperty($handshake));
    }

    /**
     * @param class-string<AppHandshakeInterface> $expectedHandshake
     */
    #[DataProvider('manifestProvider')]
    public function testManifestWithInstalledAppAndSecretProducesHandshakeWithOldSecret(Manifest $manifest, string $expectedHandshake): void
    {
        $shopUrl = 'test.shop.com';

        $factory = new HandshakeFactory(
            $shopUrl,
            static::getContainer()->get(ShopIdProvider::class),
            static::getContainer()->get(StoreClient::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('test-app');
        $app->setAppSecret('secret-123');

        $handshake = $factory->create($manifest, $app);

        static::assertInstanceOf($expectedHandshake, $handshake);

        static::assertSame('secret-123', $this->getSecretProperty($handshake));
    }

    /**
     * @return iterable<string, array{0: Manifest, 1: class-string<StoreHandshake|PrivateHandshake>}>
     */
    public static function manifestProvider(): iterable
    {
        yield 'app with manifest secret' => [
            Manifest::createFromXmlFile(__DIR__ . '/../../Manifest/_fixtures/minimal/manifest.xml'),
            PrivateHandshake::class,
        ];

        yield 'app without manifest secret' => [
            Manifest::createFromXmlFile(__DIR__ . '/../../Manifest/_fixtures/private/manifest.xml'),
            StoreHandshake::class,
        ];
    }

    /**
     * use reflection to get the private property value,
     * as by design the handshake does not expose it, but we can not check it otherwise,
     * without faking external requests for the store handshake
     */
    private function getSecretProperty(AppHandshakeInterface $handshake): ?string
    {
        $reflection = new \ReflectionClass($handshake);
        $property = $reflection->getProperty('currentAppSecret');

        return $property->getValue($handshake);
    }
}
