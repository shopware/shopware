<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\InAppPurchases\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('cache')]
class KeyFetcherTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetKeyWithoutDatabaseEntry(): void
    {
        $storeRequestOptionsProvider = $this->createMock(StoreRequestOptionsProvider::class);

        $storeRequestOptionsProvider->expects(static::once())
            ->method('getDefaultQueryParameters')
            ->willReturn(['storeToken' => 'test']);

        $storeRequestOptionsProvider->expects(static::once())
            ->method('getAuthenticationHeader')
            ->willReturn(['Authorization' => 'Bearer test']);

        $client = $this->createMock(ClientInterface::class);

        $client->expects(static::once())
            ->method('request')
            ->with(
                'GET',
                '/inappfeatures/jwks',
                [
                    'query' => ['storeToken' => 'test'],
                    'headers' => ['Authorization' => 'Bearer test'],
                ]
            )
            ->willReturn($this->buildResponse());

        $keyFetcher = new KeyFetcher(
            $client,
            $storeRequestOptionsProvider,
            new StaticSystemConfigService(),
            static::getContainer()->get('logger')
        );

        $key = $keyFetcher->getKey(Context::createDefaultContext());

        static::assertSame('sample-key-id', $key->getElements()[0]->kid);
    }

    public function testGetKeyWithDatabaseEntry(): void
    {
        $jwks = file_get_contents(__DIR__ . '/../../_fixtures/jwks.json');
        static::assertIsString($jwks);

        $storeRequestOptionsProvider = $this->createMock(StoreRequestOptionsProvider::class);

        $storeRequestOptionsProvider->expects(static::never())
            ->method('getDefaultQueryParameters');

        $storeRequestOptionsProvider->expects(static::never())
            ->method('getAuthenticationHeader');

        $client = $this->createMock(ClientInterface::class);

        $client->expects(static::never())
            ->method('request');

        $keyFetcher = new KeyFetcher(
            $client,
            $storeRequestOptionsProvider,
            new StaticSystemConfigService([
                KeyFetcher::CORE_STORE_JWKS => $jwks,
            ]),
            static::getContainer()->get('logger')
        );

        $key = $keyFetcher->getKey(Context::createDefaultContext());

        static::assertSame('ce86f11b0bebb0b711394663c17f0013', $key->getElements()[0]->kid);
    }

    public function testGetKeyWithForceRefresh(): void
    {
        $storeRequestOptionsProvider = $this->createMock(StoreRequestOptionsProvider::class);

        $storeRequestOptionsProvider->expects(static::once())
            ->method('getDefaultQueryParameters')
            ->willReturn(['storeToken' => 'test']);

        $storeRequestOptionsProvider->expects(static::once())
            ->method('getAuthenticationHeader')
            ->willReturn(['Authorization' => 'Bearer test']);

        $client = $this->createMock(ClientInterface::class);

        $client->expects(static::once())
            ->method('request')
            ->with(
                'GET',
                '/inappfeatures/jwks',
                [
                    'query' => ['storeToken' => 'test'],
                    'headers' => ['Authorization' => 'Bearer test'],
                ]
            )
            ->willReturn($this->buildResponse());

        $keyFetcher = new KeyFetcher(
            $client,
            $storeRequestOptionsProvider,
            new StaticSystemConfigService(),
            static::getContainer()->get('logger')
        );

        $key = $keyFetcher->getKey(Context::createDefaultContext(), true);

        static::assertSame('sample-key-id', $key->getElements()[0]->kid);
    }

    private function getKey(): string
    {
        return '{"keys": [{"kty": "RSA", "kid": "sample-key-id", "use": "sig", "alg": "RS256", "n": "sample-n", "e": "AQAB"}]}';
    }

    private function buildResponse(): Response
    {
        return new Response(200, [], $this->getKey());
    }
}
