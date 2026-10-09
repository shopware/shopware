<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\SystemInstallListener;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemInstallListener::class)]
class SystemInstallListenerTest extends TestCase
{
    public function testCreatesIndicesOnInstall(): void
    {
        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $indexer->expects($this->once())->method('createIndices');

        $listener = new SystemInstallListener($indexer);
        $listener(new SystemInstallCompletedEvent(Context::createCLIContext()));
    }

    public function testDoesNotFailInstallWhenCreateIndicesThrows(): void
    {
        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $indexer->expects($this->once())
            ->method('createIndices')
            ->willThrowException(new \RuntimeException('OpenSearch is down'));

        $listener = new SystemInstallListener($indexer);
        $listener(new SystemInstallCompletedEvent(Context::createCLIContext()));
    }
}
