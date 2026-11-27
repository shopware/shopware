<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaIndexer::class)]
class MediaIndexerTest extends TestCase
{
    public function testOldMessagesDrainWithoutProducingMoreWork(): void
    {
        $indexer = new MediaIndexer();
        $indexer->handle(new MediaIndexingMessage(['old-message']));

        static::assertSame('media.indexer', $indexer->getName());
        static::assertSame(0, $indexer->getTotal());
        static::assertNull($indexer->iterate(['offset' => 50]));
        static::assertNull($indexer->update(static::createStub(EntityWrittenContainerEvent::class)));
    }
}
