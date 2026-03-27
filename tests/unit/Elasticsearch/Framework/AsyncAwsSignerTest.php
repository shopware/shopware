<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\CredentialProvider;
use AsyncAws\Core\Credentials\Credentials;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\AsyncAwsSigner;

/**
 * @internal
 */
#[CoversClass(AsyncAwsSigner::class)]
class AsyncAwsSignerTest extends TestCase
{
    private MockObject&LoggerInterface $logger;

    private MockObject&CredentialProvider $credentialProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->credentialProvider = $this->createMock(CredentialProvider::class);
    }

    public function testInvokeSignsRequestSuccessfully(): void
    {
        $configuration = Configuration::create([
            'region' => 'us-east-1',
            'accessKeyId' => 'key',
            'accessKeySecret' => 'secret',
        ]);

        $credentialProvider = $this->createMock(CredentialProvider::class);
        $credentialProvider->method('getCredentials')->willReturn(new Credentials('key', 'secret'));
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->with(static::callback(static function ($request): bool {
                return $request instanceof \Psr\Http\Message\RequestInterface
                    && (string) $request->getUri() === 'https://example.com/test'
                    && $request->hasHeader('Authorization');
            }))
            ->willReturn(new Response(200));

        $signer = new AsyncAwsSigner($configuration, $this->logger, 'es', 'us-east-1', $credentialProvider, $client);

        $request = (new HttpFactory())->createRequest('GET', 'https://example.com/test');

        $result = $signer->sendRequest($request);

        static::assertSame(200, $result->getStatusCode());
    }

    public function testInvokeLogsErrorOnFailure(): void
    {
        $configuration = Configuration::create([
            'region' => 'test',
        ]);

        $signer = new AsyncAwsSigner(
            $configuration,
            $this->logger,
            'es',
            'test',
            $this->credentialProvider,
            $this->createMock(ClientInterface::class)
        );

        $this->logger->expects($this->once())
            ->method('error')
            ->with(static::stringContains('Error signing request'));

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('Could not get AWS credentials');

        $request = (new HttpFactory())->createRequest('GET', 'https://example.com/test');

        $signer->sendRequest($request);
    }
}
