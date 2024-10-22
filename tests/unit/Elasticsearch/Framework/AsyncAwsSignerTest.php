<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use AsyncAws\Core\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
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
    public function testInvokeSignsRequestSuccessfully(): void
    {
        $configuration = Configuration::create([
            'region' => 'us-east-1',
            'accessKeyId' => 'key',
            'accessKeySecret' => 'secret',
        ]);
        $signer = new AsyncAwsSigner($configuration, $this->createMock(LoggerInterface::class), 'es', 'us-east-1');

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
            'region' => 'us-east-1',
        ]);
        $logger = $this->createMock(LoggerInterface::class);
        $signer = new AsyncAwsSigner($configuration, $logger, 'es', 'us-east-1');

        $logger->expects(static::once())
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
