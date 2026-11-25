<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutRootSourceReader::class)]
class LayoutRootSourceReaderTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('reads the root source from a matching in-batch command and never touches the store')]
    public function testReadsRootSourceFromInBatchCommand(): void
    {
        $layoutId = $this->ids->get('layout');

        $registry = static::createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $reader = new LayoutRootSourceReader($registry);

        $command = $this->layoutCommand(InsertCommand::class, ContentLayoutDefinition::ENTITY_NAME, $layoutId, true, 'product');

        static::assertSame('product', $reader->read($layoutId, [$command], Context::createDefaultContext()));
    }

    #[TestDox('converts a binary uuid to hex before matching the in-batch command')]
    public function testConvertsBinaryUuidToHexBeforeMatching(): void
    {
        $hex = $this->ids->get('layout');

        $registry = static::createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $reader = new LayoutRootSourceReader($registry);

        $command = $this->layoutCommand(InsertCommand::class, ContentLayoutDefinition::ENTITY_NAME, $hex, true, 'category');

        static::assertSame('category', $reader->read(Uuid::fromHexToBytes($hex), [$command], Context::createDefaultContext()));
    }

    /**
     * @param class-string<WriteCommand> $commandClass
     */
    #[DataProvider('fallsBackToStoreProvider')]
    #[TestDox('falls back to the committed root source for $_dataName')]
    public function testFallsBackToCommittedRootSource(string $commandClass, string $readLayoutId, string $commandPrimaryKey, bool $setsRootSource): void
    {
        $layout = static::createStub(ContentLayoutEntity::class);
        $layout->method('getRootSource')->willReturn('category');

        $reader = new LayoutRootSourceReader($this->registryReturning($this->searchResult($layout), $readLayoutId));

        $command = $this->layoutCommand($commandClass, ContentLayoutDefinition::ENTITY_NAME, $commandPrimaryKey, $setsRootSource, 'product');

        static::assertSame('category', $reader->read($readLayoutId, [$command], Context::createDefaultContext()));
    }

    #[DataProvider('returnsNullForInvalidLayoutIdProvider')]
    #[TestDox('returns null for $_dataName without touching the store')]
    public function testReturnsNullForInvalidLayoutId(?string $invalidId): void
    {
        $registry = static::createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $reader = new LayoutRootSourceReader($registry);

        static::assertNull($reader->read($invalidId, [], Context::createDefaultContext()));
    }

    #[TestDox('returns null when the layout is not loadable from the committed store')]
    public function testReturnsNullWhenLayoutNotLoadable(): void
    {
        $layoutId = $this->ids->get('layout');
        $reader = new LayoutRootSourceReader($this->registryReturning($this->searchResult(null), $layoutId));

        static::assertNull($reader->read($layoutId, [], Context::createDefaultContext()));
    }

    #[TestDox('falls back to the committed root source when the matching in-batch command sets a non-string root_source')]
    public function testFallsBackToStoreForNonStringInBatchRootSource(): void
    {
        $layoutId = $this->ids->get('layout');

        $layout = static::createStub(ContentLayoutEntity::class);
        $layout->method('getRootSource')->willReturn('category');

        $reader = new LayoutRootSourceReader($this->registryReturning($this->searchResult($layout), $layoutId));

        // hasField(ROOT_SOURCE_FIELD) is true, but the payload value is a non-string (a malformed array), so the
        // reader's \is_string() guard must skip it and defer to the committed store. A non-null value is required:
        // null would fall through the `?? fromStore()` regardless and would not catch the guard's removal.
        $command = $this->layoutCommand(InsertCommand::class, ContentLayoutDefinition::ENTITY_NAME, $layoutId, true, ['not' => 'a string']);

        static::assertSame('category', $reader->read($layoutId, [$command], Context::createDefaultContext()));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function returnsNullForInvalidLayoutIdProvider(): iterable
    {
        yield 'a non-string (null) layout id' => [null];
        yield 'an empty string layout id' => [''];
    }

    /**
     * @return iterable<string, array{class-string<WriteCommand>, string, string, bool}>
     */
    public static function fallsBackToStoreProvider(): iterable
    {
        $ids = new IdsCollection();
        $layoutId = $ids->get('layout');

        yield 'a matching update that does not set root_source' => [UpdateCommand::class, $layoutId, $layoutId, false];
        yield 'a command whose primary key does not match the layout' => [InsertCommand::class, $layoutId, $ids->get('mismatch'), true];
        yield 'a delete command for the layout' => [DeleteCommand::class, $layoutId, $layoutId, false];
    }

    /**
     * @param class-string<WriteCommand> $commandClass
     */
    private function layoutCommand(string $commandClass, string $entityName, string $primaryKeyHex, bool $setsRootSource, mixed $rootSourceValue): WriteCommand
    {
        $command = static::createStub($commandClass);
        $command->method('getEntityName')->willReturn($entityName);
        $command->method('getDecodedPrimaryKey')->willReturn(['id' => $primaryKeyHex]);
        $command->method('hasField')->willReturnCallback(
            static fn (string $field): bool => $setsRootSource && $field === ContentLayoutDefinition::ROOT_SOURCE_FIELD
        );
        $command->method('getPayload')->willReturn(
            $setsRootSource ? [ContentLayoutDefinition::ROOT_SOURCE_FIELD => $rootSourceValue] : []
        );

        return $command;
    }

    /**
     * @param EntitySearchResult<EntityCollection<Entity>> $result
     */
    private function registryReturning(EntitySearchResult $result, string $expectedLayoutId): DefinitionInstanceRegistry
    {
        /** @var StaticEntityRepository<EntityCollection<Entity>> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($result, $expectedLayoutId): EntitySearchResult {
                static::assertSame([$expectedLayoutId], $criteria->getIds());

                return $result;
            },
        ]);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        return $registry;
    }

    /**
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    private function searchResult(?ContentLayoutEntity $layout): EntitySearchResult
    {
        /** @var EntityCollection<Entity> $entities */
        $entities = new EntityCollection($layout === null ? [] : [$layout]);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($entities);

        return $result;
    }
}
