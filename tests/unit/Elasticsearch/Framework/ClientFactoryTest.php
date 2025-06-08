<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
        static::assertSame('test', $client->transport->getConnection()->getHost());
        static::assertSame('http', $client->transport->getConnection()->getTransportSchema());
    }

    public function testBuildHttpsClient(): void
    {
        $client = ClientFactory::createClient('https://test', new NullLogger(), true, ['verify_server_cert' => true, 'cert_path' => 'cert.pem', 'cert_key_path' => 'cert.key', 'sigV4' => ['enabled' => true]]);
        static::assertSame('test', $client->transport->getConnection()->getHost());
        static::assertSame('https', $client->transport->getConnection()->getTransportSchema());
    }

    public function testBuildHttpsClientWithSigV4CredentialProvider(): void
    {
        $client = ClientFactory::createClient('https://test', new NullLogger(), true, ['verify_server_cert' => true, 'cert_path' => 'cert.pem', 'cert_key_path' => 'cert.key', 'sigV4' => ['enabled' => true, 'region' => 'us-east-2', 'service' => 'es', 'credentials_provider' => ['key_id' => 'key', 'secret_key' => 'secret']]]);
        static::assertSame('test', $client->transport->getConnection()->getHost());
        static::assertSame('https', $client->transport->getConnection()->getTransportSchema());
    }
}
