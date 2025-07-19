<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Lcobucci\JWT\ClaimsFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\JWT\JWTDecoder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(InAppPurchaseProvider::class)]
#[Package('checkout')]
class InAppPurchaseProviderTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private TestHandler $logger;

    private StaticSystemConfigService $config;

    private string $validJwks;

    private string $invalidJwks;

    private InAppPurchase $iap;

    protected function setUp(): void
    {
        $validJwks = file_get_contents(__DIR__ . '/../../../JWT/_fixtures/valid-jwks.json');
        $invalidJwks = file_get_contents(__DIR__ . '/../../../JWT/_fixtures/invalid-jwks.json');
        static::assertIsString($validJwks);
        static::assertIsString($invalidJwks);
        $this->validJwks = $validJwks;
        $this->invalidJwks = $invalidJwks;

        $this->client = $this->createMock(ClientInterface::class);
        $this->logger = new TestHandler();
        $this->config = new StaticSystemConfigService([
            StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN => 'example.com',
            KeyFetcher::CORE_STORE_JWKS => $this->validJwks,
        ]);

        $this->iap = new InAppPurchase(
            new InAppPurchaseProvider(
                $this->config,
                new JWTDecoder(),
                new KeyFetcher(
                    $this->client,
                    $this->createMock(StoreRequestOptionsProvider::class),
                    $this->config,
                    new Logger('test', [$this->logger])
                ),
                new Logger('test', [$this->logger]),
            )
        );
    }

    public function testActivePurchases(): void
    {
        $this->config->set(InAppPurchaseProvider::CONFIG_STORE_IAP_KEY, $this->formatConfigKey([
            'ActiveFeature1' => 'Extension1',
            'ActiveFeature2' => 'Extension1',
            'ActiveFeature3' => 'Extension2',
        ]));

        static::assertSame(['Extension1-ActiveFeature1', 'Extension1-ActiveFeature2', 'Extension2-ActiveFeature3'], $this->iap->formatPurchases());
        static::assertEquals([], $this->logger->getRecords());
        static::assertSame(['ActiveFeature1', 'ActiveFeature2'], $this->iap->getByExtension('Extension1'));
        static::assertSame(['ActiveFeature3'], $this->iap->getByExtension('Extension2'));
        static::assertSame([], $this->iap->getByExtension('Extension3'));

        static::assertTrue($this->iap->isActive('Extension1', 'ActiveFeature1'));
        static::assertTrue($this->iap->isActive('Extension1', 'ActiveFeature2'));
        static::assertTrue($this->iap->isActive('Extension2', 'ActiveFeature3'));
        static::assertFalse($this->iap->isActive('Extension2', 'this-one-is-not'));
    }

    public function testExpiredPurchase(): void
    {
        $this->config->set(InAppPurchaseProvider::CONFIG_STORE_IAP_KEY, $this->formatConfigKey(
            ['ExpiredFeature' => 'Extension'],
            '2000-01-01',
        ));

        static::assertSame([], $this->iap->formatPurchases());
        static::assertEquals([], $this->logger->getRecords());
        static::assertSame([], $this->iap->getByExtension('extension'));
        static::assertFalse($this->iap->isActive('Extension7', 'ExpiredFeature'));
    }

    public function testEmptySystemConfig(): void
    {
        $this->config = new StaticSystemConfigService();

        static::assertEmpty($this->iap->formatPurchases());
        static::assertEquals([], $this->logger->getRecords());
    }

    public function testInvalidSystemConfig(): void
    {
        $this->config->set(InAppPurchaseProvider::CONFIG_STORE_IAP_KEY, 'not a json');

        static::assertEmpty($this->iap->formatPurchases());
        static::assertEquals([], $this->logger->getRecords());
    }

    public function testGetPurchasesWithInvalidKeyRetriesOnce(): void
    {
        $this->config->set(KeyFetcher::CORE_STORE_JWKS, $this->invalidJwks);
        $this->config->set(InAppPurchaseProvider::CONFIG_STORE_IAP_KEY, $this->formatConfigKey([
            'ActiveFeature1' => 'Extension1',
            'ActiveFeature2' => 'Extension1',
            'ActiveFeature3' => 'Extension2',
        ]));

        $this->client
            ->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], $this->validJwks));

        static::assertSame(['Extension1-ActiveFeature1', 'Extension1-ActiveFeature2', 'Extension2-ActiveFeature3'], $this->iap->formatPurchases());
        static::assertEquals([], $this->logger->getRecords());
        static::assertSame(['ActiveFeature1', 'ActiveFeature2'], $this->iap->getByExtension('Extension1'));
        static::assertSame(['ActiveFeature3'], $this->iap->getByExtension('Extension2'));
        static::assertSame([], $this->iap->getByExtension('Extension3'));

        static::assertTrue($this->iap->isActive('Extension1', 'ActiveFeature1'));
        static::assertTrue($this->iap->isActive('Extension1', 'ActiveFeature2'));
        static::assertTrue($this->iap->isActive('Extension2', 'ActiveFeature3'));
        static::assertFalse($this->iap->isActive('Extension2', 'this-one-is-not'));
    }

    public function testGetPurchasesWithInvalidKeyRetriesMultiple(): void
    {
        $this->config->set(KeyFetcher::CORE_STORE_JWKS, $this->invalidJwks);
        $this->config->set(InAppPurchaseProvider::CONFIG_STORE_IAP_KEY, $this->formatConfigKey([
            'ActiveFeature1' => 'Extension1',
            'ActiveFeature2' => 'Extension1',
            'ActiveFeature3' => 'Extension2',
        ]));

        $this->client
            ->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], $this->invalidJwks));

        static::assertSame([], $this->iap->formatPurchases());

        static::assertCount(1, $this->logger->getRecords());
        $record = $this->logger->getRecords()[0];
        static::assertSame('Unable to decode In-App purchases for extension "{extension}": {message}', $record->message);
        static::assertSame('Extension1', $record->context['extension']);
        static::assertSame('Invalid JWT: Key ID (kid) could not be found', $record->context['message']);
    }

    public function testGetPurchasesWithoutJWKS(): void
    {
        $this->config->set(KeyFetcher::CORE_STORE_JWKS, '');
        $this->config->set(InAppPurchaseProvider::CONFIG_STORE_IAP_KEY, $this->formatConfigKey([
            'ActiveFeature1' => 'Extension1',
            'ActiveFeature2' => 'Extension1',
            'ActiveFeature3' => 'Extension2',
        ]));

        $this->client
            ->expects($this->once())
            ->method('request')
            ->willReturn(new Response(500));

        static::assertSame([], $this->iap->formatPurchases());

        static::assertCount(2, $this->logger->getRecords());
        $record = $this->logger->getRecords()[0];
        static::assertSame('Could not fetch the JWKS from the SBP', $record->message);
        $record = $this->logger->getRecords()[1];
        static::assertSame('Unable to decode In-App purchases: {message}', $record->message);
        static::assertSame('Unable to retrieve JWKS key', $record->context['message']);
    }

    /**
     * @param array<string, string> $purchases
     */
    private function formatConfigKey(array $purchases, string $expiresAt = '2099-01-01'): string
    {
        $formattedActivePurchases = [];
        foreach ($purchases as $identifier => $extensionName) {
            $formattedActivePurchases[$extensionName][$extensionName . $identifier] = [
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
     * @param array<string, array<string, int|string>> $payload
     */
    private function generateJwt(array $payload): string
    {
        $builder = Builder::new(new JoseEncoder(), new class implements ClaimsFormatter {
            public function formatClaims(array $claims): array
            {
                return $claims;
            }
        });

        foreach ($payload as $i => $iap) {
            static::assertNotEmpty($i);
            $builder = $builder->withClaim((string) $i, $iap);
        }

        return $builder
            ->withHeader('kid', 'ibvOgtMeMhihwgJvEw9yxXOs1YX07H34')
            ->getToken(new Sha256(), InMemory::file(__DIR__ . '/../../../JWT/_fixtures/private.pem'))
            ->toString();
    }
}
