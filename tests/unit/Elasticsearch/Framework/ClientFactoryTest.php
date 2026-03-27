<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use GuzzleHttp\Client as GuzzleClient;
use OpenSearch\Client;
use OpenSearch\HttpTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;
use Shopware\Elasticsearch\Framework\ClientFactory;

/**
 * @internal
 */
#[CoversClass(ClientFactory::class)]
class ClientFactoryTest extends TestCase
{
    public function testBuildClient(): void
    {
        $client = ClientFactory::createClient('test', new NullLogger(), false, ['verify_server_cert' => false, 'sigV4' => ['enabled' => false]]);
        $config = $this->getGuzzleConfig($client);

        static::assertSame('http://test:9200/', (string) $config['base_uri']);
        static::assertFalse($config['verify']);
    }

    public function testBuildClientWithoutConfiguredHostFallsBackToLocalhost(): void
    {
        $client = ClientFactory::createClient('', new NullLogger(), false, ['verify_server_cert' => false, 'sigV4' => ['enabled' => false]]);

        static::assertSame('http://localhost:9200/', (string) $this->getGuzzleConfig($client)['base_uri']);
    }

    public function testBuildHttpsClient(): void
    {
        $client = ClientFactory::createClient('https://test', new NullLogger(), true, ['verify_server_cert' => true, 'cert_path' => 'cert.pem', 'cert_key_path' => 'cert.key', 'sigV4' => ['enabled' => true]]);
        $config = $this->getGuzzleConfig($client);

        static::assertSame('https://test:9200/', (string) $config['base_uri']);
        static::assertTrue($config['verify']);
        static::assertSame(['cert.pem', ''], $config['cert']);
        static::assertSame(['cert.key', ''], $config['ssl_key']);
    }

    public function testBuildHttpsClientWithSigV4CredentialProvider(): void
    {
        $client = ClientFactory::createClient('https://test', new NullLogger(), true, ['verify_server_cert' => true, 'cert_path' => 'cert.pem', 'cert_key_path' => 'cert.key', 'sigV4' => ['enabled' => true, 'region' => 'us-east-2', 'service' => 'es', 'credentials_provider' => ['key_id' => 'key', 'secret_key' => 'secret']]]);

        static::assertSame('https://test:9200/', (string) $this->getGuzzleConfig($client)['base_uri']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getGuzzleConfig(Client $client): array
    {
        $transportProperty = new \ReflectionProperty($client, 'httpTransport');
        $transport = $transportProperty->getValue($client);

        static::assertInstanceOf(HttpTransport::class, $transport);

        $httpClientProperty = new \ReflectionProperty($transport, 'client');
        $httpClient = $httpClientProperty->getValue($transport);
        $guzzleClient = $this->resolveGuzzleClient($httpClient);
        $property = new \ReflectionProperty(GuzzleClient::class, 'config');

        return $property->getValue($guzzleClient);
    }

    private function resolveGuzzleClient(mixed $httpClient): GuzzleClient
    {
        if ($httpClient instanceof GuzzleClient) {
            return $httpClient;
        }

        static::assertInstanceOf(ClientInterface::class, $httpClient);
        static::assertTrue(property_exists($httpClient, 'client'));

        $property = new \ReflectionProperty($httpClient, 'client');

        return $this->resolveGuzzleClient($property->getValue($httpClient));
    }
}
