<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\JWT\JWTDecoder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(InAppPurchaseProvider::class)]
#[Package('checkout')]
class InAppPurchaseProviderTest extends TestCase
{
    public function testActivePurchases(): void
    {
        $jwks = file_get_contents(__DIR__ . '/../../../JWT/_fixtures/valid-jwks.json');
        static::assertIsString($jwks);
        $jwks = trim($jwks);

        $config = new StaticSystemConfigService([
            InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => $this->formatConfigKey([
                'ActiveFeature1' => 'Extension1',
                'ActiveFeature2' => 'Extension1',
                'ActiveFeature3' => 'Extension2',
            ]),
            'core.store.licenseHost' => 'example.com',
            KeyFetcher::CORE_STORE_JWKS => $jwks,
        ]);

        $iap = new InAppPurchase(
            new InAppPurchaseProvider(
                $config,
                new JWTDecoder(),
                new KeyFetcher(
                    $this->createMock(ClientInterface::class),
                    $this->createMock(AbstractStoreRequestOptionsProvider::class),
                    $config,
                    $this->createMock(LoggerInterface::class)
                )
            )
        );

        static::assertTrue($iap->isActive('Extension1', 'ActiveFeature1'));
        static::assertTrue($iap->isActive('Extension1', 'ActiveFeature2'));
        static::assertTrue($iap->isActive('Extension2', 'ActiveFeature3'));
        static::assertFalse($iap->isActive('Extension2', 'this-one-is-not'));

        static::assertSame(['Extension1-ActiveFeature1', 'Extension1-ActiveFeature2', 'Extension2-ActiveFeature3'], $iap->formatPurchases());
        static::assertSame(['ActiveFeature1', 'ActiveFeature2'], $iap->getByExtension('Extension1'));
        static::assertSame(['ActiveFeature3'], $iap->getByExtension('Extension2'));
        static::assertSame([], $iap->getByExtension('Extension3'));
    }

    public function testExpiredPurchase(): void
    {
        $jwks = file_get_contents(__DIR__ . '/../../../JWT/_fixtures/valid-jwks.json');
        static::assertIsString($jwks);

        $config = new StaticSystemConfigService([
            InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => $this->formatConfigKey([
                'ExpiredFeature' => 'Extension',
            ], '2000-01-01'),
            'core.store.licenseHost' => 'example.com',
            KeyFetcher::CORE_STORE_JWKS => $jwks,
        ]);

        $iap = new InAppPurchase(
            new InAppPurchaseProvider(
                $config,
                new JWTDecoder(),
                new KeyFetcher(
                    $this->createMock(ClientInterface::class),
                    $this->createMock(AbstractStoreRequestOptionsProvider::class),
                    $config,
                    $this->createMock(LoggerInterface::class)
                )
            )
        );

        static::assertFalse($iap->isActive('Extension7', 'ExpiredFeature'));
        static::assertSame([], $iap->formatPurchases());
        static::assertSame([], $iap->getByExtension('extension'));
    }

    public function testEmptySystemConfig(): void
    {
        $iap = new InAppPurchase(
            new InAppPurchaseProvider(
                new StaticSystemConfigService(),
                new JWTDecoder(),
                new KeyFetcher(
                    $this->createMock(ClientInterface::class),
                    $this->createMock(StoreRequestOptionsProvider::class),
                    $this->createMock(SystemConfigService::class),
                    $this->createMock(LoggerInterface::class)
                ),
            )
        );

        static::assertEmpty($iap->formatPurchases());
    }

    public function testInvalidSystemConfig(): void
    {
        $iap = new InAppPurchase(
            new InAppPurchaseProvider(
                new StaticSystemConfigService([InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => 'not a json']),
                new JWTDecoder(),
                new KeyFetcher(
                    $this->createMock(ClientInterface::class),
                    $this->createMock(StoreRequestOptionsProvider::class),
                    $this->createMock(SystemConfigService::class),
                    $this->createMock(LoggerInterface::class)
                ),
            )
        );

        static::assertEmpty($iap->formatPurchases());
    }

    public function testGetPurchasesWithInvalidKeyRetriesOnce(): void
    {
        $invalidJwks = file_get_contents(__DIR__ . '/../../../JWT/_fixtures/invalid-jwks.json');
        static::assertIsString($invalidJwks);

        $config = new StaticSystemConfigService([
            InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => $this->formatConfigKey([
                'ActiveFeature1' => 'Extension1',
                'ActiveFeature2' => 'Extension1',
                'ActiveFeature3' => 'Extension2',
            ]),
            'core.store.licenseHost' => 'example.com',
            KeyFetcher::CORE_STORE_JWKS => $invalidJwks,
        ]);

        $validJwks = file_get_contents(__DIR__ . '/../../../JWT/_fixtures/valid-jwks.json');
        static::assertIsString($validJwks);

        $client = $this->createMock(ClientInterface::class);
        $client->expects(static::once())
            ->method('request')
            ->willReturn(new Response(200, [], $validJwks));

        $iap = new InAppPurchase(
            new InAppPurchaseProvider(
                $config,
                new JWTDecoder(),
                new KeyFetcher(
                    $client,
                    $this->createMock(StoreRequestOptionsProvider::class),
                    $config,
                    $this->createMock(LoggerInterface::class)
                ),
            )
        );

        static::assertTrue($iap->isActive('Extension1', 'ActiveFeature1'));
        static::assertTrue($iap->isActive('Extension1', 'ActiveFeature2'));
        static::assertTrue($iap->isActive('Extension2', 'ActiveFeature3'));
        static::assertFalse($iap->isActive('Extension2', 'this-one-is-not'));

        static::assertSame(['Extension1-ActiveFeature1', 'Extension1-ActiveFeature2', 'Extension2-ActiveFeature3'], $iap->formatPurchases());
        static::assertSame(['ActiveFeature1', 'ActiveFeature2'], $iap->getByExtension('Extension1'));
        static::assertSame(['ActiveFeature3'], $iap->getByExtension('Extension2'));
        static::assertSame([], $iap->getByExtension('Extension3'));
    }

    public function testGetPurchasesWithInvalidKeyRetriesMultiple(): void
    {
        $invalidJwks = file_get_contents(__DIR__ . '/../../../JWT/_fixtures/invalid-jwks.json');
        static::assertIsString($invalidJwks);

        $config = new StaticSystemConfigService([
            InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => $this->formatConfigKey([
                'ActiveFeature1' => 'Extension1',
                'ActiveFeature2' => 'Extension1',
                'ActiveFeature3' => 'Extension2',
            ]),
            'core.store.licenseHost' => 'example.com',
            KeyFetcher::CORE_STORE_JWKS => $invalidJwks,
        ]);

        $client = $this->createMock(ClientInterface::class);
        $client->expects(static::once())
            ->method('request')
            ->willReturn(new Response(200, [], $invalidJwks));

        $iap = new InAppPurchase(
            new InAppPurchaseProvider(
                $config,
                new JWTDecoder(),
                new KeyFetcher(
                    $client,
                    $this->createMock(StoreRequestOptionsProvider::class),
                    $config,
                    $this->createMock(LoggerInterface::class)
                ),
            )
        );

        static::assertSame([], $iap->formatPurchases());
    }

    /**
     * @param array<string, string> $purchases
     */
    private function formatConfigKey(array $purchases, string $expiresAt = '2099-01-01'): string
    {
        $formattedActivePurchases = [];
        foreach ($purchases as $identifier => $extensionName) {
            $formattedActivePurchases[$extensionName][] = [
                'identifier' => $identifier,
                'nextBookingDate' => $expiresAt,
                'sub' => 'example.com',
                'quantity' => 1,
            ];
        }
        foreach ($formattedActivePurchases as $extensionName => $purchases) {
            $formattedActivePurchases[$extensionName] = $this->generateJwt($purchases);
        }

        return \json_encode($formattedActivePurchases) ?: '';
    }

    /**
     * @param array<int, array<string, int|string>> $payload
     */
    private function generateJwt(array $payload): string
    {
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode('{"alg":"RS256","typ":"JWT","kid":"ce86f11b0bebb0b711394663c17f0013"}'));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload, \JSON_THROW_ON_ERROR)));
        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, 'secret', true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }
}
