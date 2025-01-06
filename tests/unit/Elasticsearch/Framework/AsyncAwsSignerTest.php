<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\CredentialProvider;
use AsyncAws\Core\Credentials\Credentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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
        $signer = new AsyncAwsSigner($configuration, $this->logger, 'es', 'us-east-1', $credentialProvider);

        $request = [
            'http_method' => 'GET',
            'headers' => ['Host' => ['https://example.com']],
            'uri' => '/test',
            'scheme' => 'https',
            'body' => '',
            'query_string' => '',
        ];

        $result = ($signer)($request);
        $result = $result->offsetGet('transfer_stats');

        static::assertSame('https://example.com/test', $result['url']);
    }

    public function testInvokeLogsErrorOnFailure(): void
    {
        $configuration = Configuration::create([
            'region' => 'test',
        ]);

        $signer = new AsyncAwsSigner($configuration, $this->logger, 'es', 'test', $this->credentialProvider);

        $this->logger->expects(static::once())
            ->method('error')
            ->with(static::stringContains('Error signing request'));

        $this->expectException(ElasticsearchException::class);
        $this->expectExceptionMessage('Could not get AWS credentials');

        $request = [
            'http_method' => 'GET',
            'headers' => ['Host' => ['https://example.com']],
            'uri' => '/test',
            'scheme' => 'https',
            'body' => '',
            'query_string' => '',
        ];

        ($signer)($request);
    }
}
