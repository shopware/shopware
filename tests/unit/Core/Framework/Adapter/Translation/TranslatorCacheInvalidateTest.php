<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Translation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Translation\TranslatorCacheInvalidate;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\Snippet\SnippetEvents;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(TranslatorCacheInvalidate::class)]
class TranslatorCacheInvalidateTest extends TestCase
{
    private Connection&MockObject $connection;

    private CacheInvalidator&MockObject $cacheInvalidator;

    private TranslatorCacheInvalidate $translatorCacheInvalidate;

    private Context $context;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('cache_rework', $this);

        $this->connection = $this->createMock(Connection::class);
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->context = Context::createDefaultContext();

        $this->translatorCacheInvalidate = new TranslatorCacheInvalidate(
            $this->cacheInvalidator,
            $this->connection
        );
    }

    public function testSubscribedEvents(): void
    {
        $subscribedEvents = TranslatorCacheInvalidate::getSubscribedEvents();

        static::assertEquals([
            SnippetEvents::SNIPPET_WRITTEN_EVENT => 'invalidate',
            SnippetEvents::SNIPPET_DELETED_EVENT => 'invalidate',
            SnippetEvents::SNIPPET_SET_DELETED_EVENT => 'invalidate',
        ], $subscribedEvents);
    }

    public function testInvalidateWithSnippetEvent(): void
    {
        $ids = new IdsCollection();

        $expectedSnippetSetIds = [
            $ids->get('snippetSet1'),
            $ids->get('snippetSet2'),
        ];

        $snippetIds = [Uuid::randomHex(), Uuid::randomHex()];

        $this->connection->expects(static::once())->method('fetchFirstColumn')->with(
            'SELECT LOWER(HEX(snippet_set_id)) FROM snippet WHERE HEX(id) IN (:ids)',
            ['ids' => $snippetIds],
            ['ids' => ArrayParameterType::BINARY],
        )->willReturn($expectedSnippetSetIds);

        $writeResults = [];
        foreach ($snippetIds as $snippetId) {
            $writeResults[] = new EntityWriteResult(
                $snippetId,
                [],
                SnippetDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            );
        }

        $event = new EntityWrittenEvent(
            SnippetDefinition::ENTITY_NAME,
            $writeResults,
            $this->context
        );

        $this->cacheInvalidator->expects(static::once())->method('invalidate')->with([
            'translation.catalog.' . $ids->get('snippetSet1'),
            'translation.catalog.' . $ids->get('snippetSet2'),
        ], false);

        $this->translatorCacheInvalidate->invalidate($event);
    }

    public function testInvalidateWithSnippetSetEvent(): void
    {
        $ids = new IdsCollection();

        $snippetSetIds = [
            $ids->get('snippetSet1'),
            $ids->get('snippetSet2'),
        ];

        $this->connection->expects(static::never())->method('fetchFirstColumn');

        $writeResults = [];
        foreach ($snippetSetIds as $snippetSetId) {
            $writeResults[] = new EntityWriteResult(
                $snippetSetId,
                [],
                SnippetSetDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            );
        }

        $event = new EntityWrittenEvent(
            SnippetSetDefinition::ENTITY_NAME,
            $writeResults,
            $this->context
        );

        $this->cacheInvalidator->expects(static::once())->method('invalidate')->with([
            'translation.catalog.' . $ids->get('snippetSet1'),
            'translation.catalog.' . $ids->get('snippetSet2'),
        ], false);

        $this->translatorCacheInvalidate->invalidate($event);
    }
}
