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
use Shopware\Elasticsearch\Framework\RoundRobinHostHttpClient;

/**
 * @internal
 */
#[CoversClass(ClientFactory::class)]
class ClientFactoryTest extends TestCase
{
    public function testBuildClient(): void
    {
        $client = ClientFactory::createClient('test', new NullLogger(), false, ['verify_server_cert' => false, 'sigV4' => ['enabled' => false]]);
        $roundRobinClient = $this->getHttpClient($client);
        $config = $this->getGuzzleConfig($roundRobinClient);

        static::assertSame(['http://test:9200/'], array_map(static fn (\Psr\Http\Message\UriInterface $uri): string => (string) $uri, $roundRobinClient->getHosts()));
        static::assertFalse($config['verify']);
    }

    public function testBuildHttpsClient(): void
    {
        $client = ClientFactory::createClient('https://test', new NullLogger(), true, ['verify_server_cert' => true, 'cert_path' => 'cert.pem', 'cert_key_path' => 'cert.key', 'sigV4' => ['enabled' => true]]);
        $roundRobinClient = $this->getHttpClient($client);
        $config = $this->getGuzzleConfig($roundRobinClient);

        static::assertSame(['https://test:9200/'], array_map(static fn (\Psr\Http\Message\UriInterface $uri): string => (string) $uri, $roundRobinClient->getHosts()));
        static::assertTrue($config['verify']);
        static::assertSame(['cert.pem', ''], $config['cert']);
        static::assertSame(['cert.key', ''], $config['ssl_key']);
    }

    public function testBuildHttpsClientWithSigV4CredentialProvider(): void
    {
        $client = ClientFactory::createClient('https://test', new NullLogger(), true, ['verify_server_cert' => true, 'cert_path' => 'cert.pem', 'cert_key_path' => 'cert.key', 'sigV4' => ['enabled' => true, 'region' => 'us-east-2', 'service' => 'es', 'credentials_provider' => ['key_id' => 'key', 'secret_key' => 'secret']]]);
        $roundRobinClient = $this->getHttpClient($client);

        static::assertSame(['https://test:9200/'], array_map(static fn (\Psr\Http\Message\UriInterface $uri): string => (string) $uri, $roundRobinClient->getHosts()));
    }

    private function getHttpClient(Client $client): RoundRobinHostHttpClient
    {
        $transportProperty = new \ReflectionProperty($client, 'httpTransport');
        $transport = $transportProperty->getValue($client);

        static::assertInstanceOf(HttpTransport::class, $transport);

        $httpClientProperty = new \ReflectionProperty($transport, 'client');
        $httpClient = $httpClientProperty->getValue($transport);

        static::assertInstanceOf(RoundRobinHostHttpClient::class, $httpClient);

        return $httpClient;
    }

    /**
     * @return array<string, mixed>
     */
    private function getGuzzleConfig(RoundRobinHostHttpClient $client): array
    {
        $property = new \ReflectionProperty($this->getGuzzleClient($client), 'config');

        return $property->getValue($this->getGuzzleClient($client));
    }

    private function getGuzzleClient(RoundRobinHostHttpClient $client): GuzzleClient
    {
        $wrappedClient = $client->getClient();

        if ($wrappedClient instanceof GuzzleClient) {
            return $wrappedClient;
        }

        $property = new \ReflectionProperty($wrappedClient, 'client');
        $innerClient = $property->getValue($wrappedClient);

        static::assertInstanceOf(ClientInterface::class, $innerClient);
        static::assertInstanceOf(GuzzleClient::class, $innerClient);

        return $innerClient;
    }
}
