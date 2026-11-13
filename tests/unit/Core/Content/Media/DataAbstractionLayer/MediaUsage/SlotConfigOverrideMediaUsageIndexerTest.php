<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\CmsElementMediaIdsProvider;
use Shopware\Core\Content\Media\Cms\ImageCmsElementResolver;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\AbstractMediaUsageIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\CmsSlotMediaIdResolver;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\SlotConfigOverrideMediaUsageIndexer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SlotConfigOverrideMediaUsageIndexer::class)]
#[CoversClass(AbstractMediaUsageIndexer::class)]
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

    public function testIgnoresOverridesWhichCanNotBeResolved(): void
    {
        $invalid = ['id' => Uuid::randomBytes(), 'language_id' => Uuid::randomBytes(), 'slot_config' => '"no overrides"'];
        $unknown = $this->createTranslation(['not-a-slot-id' => ['media' => ['source' => 'static', 'value' => Uuid::randomHex()]]]);

        $this->connection->method('fetchAllAssociative')->willReturn([$invalid, $unknown]);

        $this->createIndexer()->handle(new EntityIndexingMessage([Uuid::fromBytesToHex($invalid['id']), Uuid::fromBytesToHex($unknown['id'])]));

        static::assertSame([$invalid['id'], $unknown['id']], $this->getReplacedIds(), 'the previous usages are removed anyway');
        static::assertSame([], $this->getInsertedRows(columns: 4));
    }

    public function testIteratesTheTranslationsWithOverridesOfLiveVersions(): void
    {
        $categoryId = Uuid::randomHex();
        $this->connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $query = (new QueryBuilder($this->connection))->select('category_id')->from('category_translation');

        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('getQuery')->willReturn($query);
        $iterator->method('fetch')->willReturn([Uuid::fromHexToBytes($categoryId) => $categoryId]);
        $iteratorFactory = static::createStub(IteratorFactory::class);
        $iteratorFactory->method('createIterator')->willReturn($iterator);

        $message = $this->createIndexer($iteratorFactory)->iterate(null);

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame([$categoryId], $message->getData());
        static::assertStringContainsString('(slot_config IS NOT NULL) AND (`category_version_id` = :version)', $query->getSQL());
        static::assertSame(Uuid::fromHexToBytes(Defaults::LIVE_VERSION), $query->getParameter('version'));
    }

    public function testCanNotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->createIndexer()->getDecorated();
    }

    public function testFailsForTranslationsOfEntitiesWithoutTranslations(): void
    {
        $indexer = $this->createIndexer(definition: $this->createBrokenTranslation([]));

        $this->expectExceptionObject(DataAbstractionLayerException::missingParentForeignKey('broken'));

        $indexer->update($this->createEvent('broken_translation', ['brokenId' => Uuid::randomHex()], ['slotConfig' => []]));
    }

    public function testFailsForTranslationsWithoutTheForeignKeyOfTheirEntity(): void
    {
        $indexer = $this->createIndexer(definition: $this->createBrokenTranslation([
            new TranslationsAssociationField(CategoryTranslationDefinition::class, 'unknown_id'),
        ]));

        $this->expectExceptionObject(DataAbstractionLayerException::fieldByStorageNameNotFound('broken_translation', 'unknown_id'));

        $indexer->update($this->createEvent('broken_translation', ['brokenId' => Uuid::randomHex()], ['slotConfig' => []]));
    }

    private function createIndexer(?IteratorFactory $iteratorFactory = null, ?EntityTranslationDefinition $definition = null): SlotConfigOverrideMediaUsageIndexer
    {
        if ($definition === null) {
            $registry = new StaticDefinitionInstanceRegistry(
                [CategoryDefinition::class, CategoryTranslationDefinition::class],
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class)
            );
            $definition = $registry->get(CategoryTranslationDefinition::class);
            static::assertInstanceOf(CategoryTranslationDefinition::class, $definition);
        }

        return new SlotConfigOverrideMediaUsageIndexer(
            $iteratorFactory ?? static::createStub(IteratorFactory::class),
            $this->connection,
            new CmsSlotMediaIdResolver([new CmsElementMediaIdsProvider(new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class)), ['media'])]),
            $definition,
        );
    }

    /**
     * A translation of the entity `broken`, whose fields do not reference the translation like the DAL expects
     *
     * @param list<Field> $parentFields
     */
    private function createBrokenTranslation(array $parentFields): EntityTranslationDefinition
    {
        $parent = new class extends EntityDefinition {
            /**
             * @var list<Field>
             */
            public array $parentFields = [];

            public function getEntityName(): string
            {
                return 'broken';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([(new IdField('id', 'id'))->addFlags(new PrimaryKey()), ...$this->parentFields]);
            }
        };
        $parent->parentFields = $parentFields;

        $translation = new class extends EntityTranslationDefinition {
            public EntityDefinition $parent;

            public function getEntityName(): string
            {
                return 'broken_translation';
            }

            public function getParentDefinition(): EntityDefinition
            {
                return $this->parent;
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([]);
            }
        };
        $translation->parent = $parent;

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $parent->compile($registry);
        $translation->compile($registry);

        return $translation;
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
