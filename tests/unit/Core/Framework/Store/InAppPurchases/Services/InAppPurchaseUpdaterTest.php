<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\JWT\JWTDecoder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Store\InAppPurchase\Event\InAppPurchaseChangedEvent;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Shopware\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseUpdater::class)]
class InAppPurchaseUpdaterTest extends TestCase
{
    public function testUpdateActiveInAppPurchases(): void
    {
        $jwt = file_get_contents(__DIR__ . '../../../_fixtures/jwt.json');
        static::assertIsString($jwt);

        $jwks = file_get_contents(__DIR__ . '/../../../JWT/_fixtures/valid-jwks.json');
        static::assertIsString($jwks);

        $client = $this->createMock(ClientInterface::class);

        $client->expects(static::once())
            ->method('request')
            ->with('GET', 'https://test.com', ['query' => ['a'], 'headers' => ['b']])
            ->willReturn(new Response(200, [], $jwt));

        $systemConfig = new StaticSystemConfigService([
            'core.store.licenseHost' => 'example.com',
            InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => $jwt,
            KeyFetcher::CORE_STORE_JWKS => $jwks,
        ]);

        $optionsProvider = $this->createMock(AbstractStoreRequestOptionsProvider::class);
        $optionsProvider->expects(static::once())
            ->method('getDefaultQueryParameters')
            ->willReturn(['a']);
        $optionsProvider->expects(static::once())
            ->method('getAuthenticationHeader')
            ->willReturn(['b']);

        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::equalTo(new InAppPurchaseChangedEvent('TestApp', '["test","test2"]', $appId, $context)));

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAllKeyValue')
            ->willReturn(['TestApp' => $appId]);

        $iap = new InAppPurchase(
            new InAppPurchaseProvider(
                $systemConfig,
                new JWTDecoder(),
                new KeyFetcher(
                    $this->createMock(ClientInterface::class),
                    $this->createMock(StoreRequestOptionsProvider::class),
                    $systemConfig,
                    $this->createMock(LoggerInterface::class)
                )
            )
        );

        $service = new InAppPurchaseUpdater(
            $client,
            $systemConfig,
            'https://test.com',
            $optionsProvider,
            $iap,
            $eventDispatcher,
            $connection,
            $this->createMock(LoggerInterface::class)
        );
        $service->update($context);

        static::assertSame($jwt, $systemConfig->get('core.store.iapKey'));
    }
}
