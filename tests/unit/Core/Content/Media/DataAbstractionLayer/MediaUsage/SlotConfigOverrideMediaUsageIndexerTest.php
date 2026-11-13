<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\CmsElementMediaIdsProvider;
use Shopware\Core\Content\Media\Cms\ImageCmsElementResolver;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\CmsSlotMediaIdResolver;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\SlotConfigOverrideMediaUsageIndexer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SlotConfigOverrideMediaUsageIndexer::class)]
class SlotConfigOverrideMediaUsageIndexerTest extends TestCase
{
    private Connection&Stub $connection;

    /**
     * @var list<array<int|string, mixed>> parameters of the executed statements
     */
    private array $statements = [];

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('transactional')->willReturnCallback(fn (\Closure $closure) => $closure($this->connection));
        $this->connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params): int {
            $this->statements[] = $params;

            return 1;
        });
    }

    public function testIsNamedAfterTheTableOfItsEntity(): void
    {
        static::assertSame('category_translation_media_usage.indexer', $this->createIndexer()->getName());
    }

    public function testIndexesEntitiesWithChangedOverrides(): void
    {
        $categoryId = Uuid::randomHex();
        $primaryKey = ['categoryId' => $categoryId, 'languageId' => Uuid::randomHex()];

        $message = $this->createIndexer()->update($this->createEvent(CategoryTranslationDefinition::ENTITY_NAME, $primaryKey, ['slotConfig' => []]));

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame([$categoryId], $message->getData());
    }

    public function testIgnoresWritesWhichDoNotChangeOverrides(): void
    {
        $primaryKey = ['categoryId' => Uuid::randomHex(), 'languageId' => Uuid::randomHex()];

        static::assertNull($this->createIndexer()->update($this->createEvent(CategoryTranslationDefinition::ENTITY_NAME, $primaryKey, ['name' => 'category'])));
    }

    public function testMapsTheMediaOfOverridesToTheirOwner(): void
    {
        $slotId = Uuid::randomHex();
        $imageId = Uuid::randomHex();
        $translation = $this->createTranslation([
            $slotId => ['media' => ['source' => 'static', 'value' => $imageId]],
            Uuid::randomHex() => ['media' => ['source' => 'static', 'value' => Uuid::randomHex()]],
        ]);

        $this->connection->method('fetchAllAssociative')->willReturn([$translation]);
        $this->connection->method('fetchAllKeyValue')->willReturn([$slotId => 'image']);

        $this->createIndexer()->handle(new EntityIndexingMessage([Uuid::fromBytesToHex($translation['id'])]));

        static::assertSame([$translation['id']], $this->getReplacedIds(), 'the previous usages of the category are removed');
        static::assertSame(
            [[$translation['id'], Uuid::fromHexToBytes(Defaults::LIVE_VERSION), $translation['language_id'], Uuid::fromHexToBytes($imageId)]],
            $this->getInsertedRows(columns: 4),
            'overrides of slots which are not known are ignored'
        );
    }

    private function createIndexer(): SlotConfigOverrideMediaUsageIndexer
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class, CategoryTranslationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $definition = $registry->get(CategoryTranslationDefinition::class);
        static::assertInstanceOf(CategoryTranslationDefinition::class, $definition);

        return new SlotConfigOverrideMediaUsageIndexer(
            static::createStub(IteratorFactory::class),
            $this->connection,
            new CmsSlotMediaIdResolver([new CmsElementMediaIdsProvider(new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class)), ['media'])]),
            $definition,
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array{id: string, language_id: string, slot_config: string}
     */
    private function createTranslation(array $overrides): array
    {
        return [
            'id' => Uuid::randomBytes(),
            'language_id' => Uuid::randomBytes(),
            'slot_config' => (string) json_encode($overrides),
        ];
    }

    /**
     * @param array<string, string>|string $primaryKey
     * @param array<string, mixed> $payload
     */
    private function createEvent(string $entity, array|string $primaryKey, array $payload): EntityWrittenContainerEvent
    {
        $context = Context::createDefaultContext();
        $result = new EntityWriteResult($primaryKey, $payload, $entity, EntityWriteResult::OPERATION_UPDATE);

        return new EntityWrittenContainerEvent($context, new NestedEventCollection([new EntityWrittenEvent($entity, [$result], $context)]), []);
    }

    /**
     * @return list<string> binary ids whose usages were removed before inserting the current ones
     */
    private function getReplacedIds(): array
    {
        foreach ($this->statements as $params) {
            if (isset($params['ids']) && \is_array($params['ids'])) {
                return array_values($params['ids']);
            }
        }

        return [];
    }

    /**
     * @param positive-int $columns
     *
     * @return list<list<string>> the values of the inserted rows
     */
    private function getInsertedRows(int $columns): array
    {
        $rows = [];
        foreach ($this->statements as $params) {
            if (isset($params['ids'])) {
                continue;
            }

            foreach (array_chunk(array_values($params), $columns) as $row) {
                $rows[] = array_map(strval(...), $row);
            }
        }

        return $rows;
    }
}
