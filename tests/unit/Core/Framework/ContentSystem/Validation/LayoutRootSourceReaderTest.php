<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutRootSourceReader::class)]
class LayoutRootSourceReaderTest extends TestCase
{
    private const COLUMNS = ['id', ContentLayoutDefinition::ROOT_SOURCE_FIELD];

    private IdsCollection $ids;

    private EntityDefinition $definition;

    /**
     * @var list<array<string, string>>
     */
    private array $loadedPrimaryKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
        $this->definition = (new StaticDefinitionInstanceRegistry(
            [ContentLayoutDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        ))->getByEntityName(ContentLayoutDefinition::ENTITY_NAME);
    }

    #[TestDox('reads the root source of a layout the batch creates from the batch, without a stored query')]
    public function testReadsTheRootSourceOfALayoutCreatedInTheBatch(): void
    {
        $layoutId = $this->ids->get('layout');

        $rowLoader = $this->rowLoaderRecordingPrimaryKeys();

        $reader = new LayoutRootSourceReader($this->outcome(), $rowLoader);

        static::assertSame(
            [$layoutId => 'product'],
            $reader->rootSources([$layoutId], [$this->create($layoutId, 'product')])
        );
        static::assertSame([], $this->loadedPrimaryKeys);
    }

    #[TestDox('reads the root source of a layout the batch edits without setting it from the stored row')]
    public function testReadsTheStoredRootSourceOfALayoutEditedInTheBatch(): void
    {
        $layoutId = $this->ids->get('layout');

        $rowLoader = $this->rowLoaderRecordingPrimaryKeys();

        $reader = new LayoutRootSourceReader($this->outcome([$this->storedRow($layoutId, 'category')]), $rowLoader);

        static::assertSame(
            [$layoutId => 'category'],
            $reader->rootSources([$layoutId], [$this->update($layoutId)])
        );
        static::assertSame([], $this->loadedPrimaryKeys);
    }

    #[TestDox('reads the root source of a layout outside the batch from its stored row')]
    public function testReadsTheRootSourceOfALayoutOutsideTheBatchFromTheStoredRow(): void
    {
        $layoutId = $this->ids->get('layout');

        $reader = new LayoutRootSourceReader($this->outcome(), $this->rowLoaderReturning([$this->storedRow($layoutId, 'category')]));

        static::assertSame([$layoutId => 'category'], $reader->rootSources([$layoutId], []));
    }

    #[TestDox('reads a layout created in the batch and a stored one in one call, querying the store once for the stored one only')]
    public function testReadsInBatchAndStoredLayoutsWithOneStoredQuery(): void
    {
        $createdId = $this->ids->get('created');
        $storedId = $this->ids->get('stored');

        $rowLoader = $this->createMock(RowLoader::class);
        $rowLoader->expects($this->once())->method('load')
            ->with(ContentLayoutDefinition::ENTITY_NAME, [['id' => Uuid::fromHexToBytes($storedId)]], self::COLUMNS)
            ->willReturn([$this->storedRow($storedId, 'category')]);

        $reader = new LayoutRootSourceReader($this->outcome(), $rowLoader);

        static::assertSame(
            [$createdId => 'product', $storedId => 'category'],
            $reader->rootSources([$createdId, $storedId], [$this->create($createdId, 'product')])
        );
    }

    #[TestDox('leaves out a stored layout the batch deletes instead of reading its stored row')]
    public function testLeavesOutALayoutTheBatchDeletes(): void
    {
        $layoutId = $this->ids->get('layout');

        $rowLoader = $this->rowLoaderRecordingPrimaryKeys();

        $reader = new LayoutRootSourceReader($this->outcome([$this->storedRow($layoutId, 'category')]), $rowLoader);

        static::assertSame([], $reader->rootSources([$layoutId], [$this->delete($layoutId)]));
        static::assertSame([], $this->loadedPrimaryKeys);
    }

    #[TestDox('keys the root source by the binary id a foreign-key payload passes')]
    public function testKeysTheRootSourceByTheBinaryIdAsPassed(): void
    {
        $layoutId = Uuid::fromHexToBytes($this->ids->get('layout'));

        $reader = new LayoutRootSourceReader($this->outcome(), $this->rowLoaderReturning([]));

        static::assertSame(
            [$layoutId => 'category'],
            $reader->rootSources([$layoutId], [$this->create($this->ids->get('layout'), 'category')])
        );
    }

    #[TestDox('leaves out a layout that is neither written nor stored')]
    public function testLeavesOutALayoutThatIsNotStored(): void
    {
        $reader = new LayoutRootSourceReader($this->outcome(), $this->rowLoaderReturning([]));

        static::assertSame([], $reader->rootSources([$this->ids->get('missing')], []));
    }

    #[TestDox('leaves out a stored layout passed as an uppercase hex id, the DAL storing the lowercase form only')]
    public function testLeavesOutAStoredLayoutPassedAsUppercaseHex(): void
    {
        $layoutId = $this->ids->get('layout');

        $reader = new LayoutRootSourceReader($this->outcome(), $this->rowLoaderReturning([$this->storedRow($layoutId, 'category')]));

        static::assertSame([$layoutId => 'category'], $reader->rootSources([$layoutId], []));
        static::assertSame([], $reader->rootSources([strtoupper($layoutId)], []));
    }

    #[TestDox('leaves out a hex id of the wrong length instead of throwing, as for any id that matches no layout')]
    public function testLeavesOutAHexIdOfTheWrongLength(): void
    {
        $layoutId = $this->ids->get('layout');

        $reader = new LayoutRootSourceReader($this->outcome(), $this->rowLoaderReturning([$this->storedRow($layoutId, 'category')]));

        static::assertSame([], $reader->rootSources([substr($layoutId, 0, 30)], []));
    }

    /**
     * @param list<array<string, mixed>> $storedRows the rows the write outcome loads for the batch's stored layouts
     */
    private function outcome(array $storedRows = []): WriteOutcome
    {
        return new WriteOutcome($this->rowLoaderReturning($storedRows));
    }

    /**
     * A row loader that returns no row and records the primary keys it is asked for, so a test asserts after the
     * call that no stored row was requested; RowLoader runs no query for no ids.
     */
    private function rowLoaderRecordingPrimaryKeys(): RowLoader
    {
        $rowLoader = static::createStub(RowLoader::class);
        $rowLoader->method('load')->willReturnCallback(function (string $entity, array $primaryKeys): array {
            array_push($this->loadedPrimaryKeys, ...$primaryKeys);

            return [];
        });

        return $rowLoader;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function rowLoaderReturning(array $rows): RowLoader
    {
        $rowLoader = static::createStub(RowLoader::class);
        $rowLoader->method('load')->willReturn($rows);

        return $rowLoader;
    }

    /**
     * @return array<string, mixed>
     */
    private function storedRow(string $layoutId, string $rootSource): array
    {
        return ['id' => Uuid::fromHexToBytes($layoutId), ContentLayoutDefinition::ROOT_SOURCE_FIELD => $rootSource];
    }

    private function create(string $layoutId, string $rootSource): InsertCommand
    {
        $id = Uuid::fromHexToBytes($layoutId);

        return new InsertCommand($this->definition, ['id' => $id, ContentLayoutDefinition::ROOT_SOURCE_FIELD => $rootSource], ['id' => $id], static::createStub(EntityExistence::class), '/0');
    }

    private function update(string $layoutId): UpdateCommand
    {
        $id = Uuid::fromHexToBytes($layoutId);

        return new UpdateCommand($this->definition, ['layout' => '[]'], ['id' => $id], static::createStub(EntityExistence::class), '/0');
    }

    private function delete(string $layoutId): DeleteCommand
    {
        return new DeleteCommand($this->definition, ['id' => Uuid::fromHexToBytes($layoutId)], static::createStub(EntityExistence::class));
    }
}
