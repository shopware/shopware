<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutBindingGate;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutResolvabilityValidator;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutTreeDecoder;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(LayoutBindingGate::class)]
class LayoutBindingGateTest extends TestCase
{
    #[TestDox('maps the binding-scope violations of an unresolvable layout loaded from the committed store')]
    public function testMapsBindingViolationsForUnresolvableLayout(): void
    {
        $layout = static::createStub(ContentLayoutEntity::class);
        $layout->method('getLayout')->willReturn([new ContentElement('el-1', 'Sw:Test:RequiresEntity')]);

        $gate = new LayoutBindingGate(
            $this->registryReturning($this->searchResult($layout)),
            $this->resolvabilityReporting($this->unresolvedReport()),
            new ViolationConstraintMapper(),
            static::createStub(LayoutTreeDecoder::class),
        );

        $violations = $gate->bindingViolations(Uuid::randomHex(), [], [], Context::createDefaultContext());

        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::UnresolvedRequired->value, $violations->get(0)->getCode());
    }

    #[TestDox('converts a binary uuid to hex before loading the bound layout')]
    public function testConvertsBinaryUuidToHexBeforeLoading(): void
    {
        $hex = Uuid::randomHex();

        $layout = static::createStub(ContentLayoutEntity::class);
        $layout->method('getLayout')->willReturn([new ContentElement('el-1', 'Sw:Test:RequiresEntity')]);

        /** @var StaticEntityRepository<EntityCollection<Entity>> $repository */
        $repository = new StaticEntityRepository([
            function (Criteria $criteria) use ($hex, $layout): EntitySearchResult {
                static::assertSame([$hex], $criteria->getIds());

                return $this->searchResult($layout);
            },
        ]);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $gate = new LayoutBindingGate($registry, $this->resolvabilityReporting($this->unresolvedReport()), new ViolationConstraintMapper(), static::createStub(LayoutTreeDecoder::class));

        $violations = $gate->bindingViolations(Uuid::fromHexToBytes($hex), [], [], Context::createDefaultContext());

        static::assertCount(1, $violations);
    }

    /**
     * @param class-string<WriteCommand> $commandClass
     */
    #[DataProvider('resolvesInBatchTreeFromMatchingCommandProvider')]
    #[TestDox('resolves the in-batch tree from a matching $_dataName carrying the layout column and skips the store')]
    public function testResolvesInBatchTreeFromMatchingCommand(string $commandClass): void
    {
        $layoutId = Uuid::randomHex();

        $decoder = static::createStub(LayoutTreeDecoder::class);
        $decoder->method('decode')->willReturn([new ContentElement('el-1', 'Sw:Test:RequiresEntity')]);

        $registry = static::createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $gate = new LayoutBindingGate($registry, $this->resolvabilityReporting($this->unresolvedReport()), new ViolationConstraintMapper(), $decoder);

        $command = $this->layoutCommand($commandClass, ContentLayoutDefinition::ENTITY_NAME, $layoutId, true, 'encoded-tree');

        $violations = $gate->bindingViolations($layoutId, [], [$command], Context::createDefaultContext());

        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::UnresolvedRequired->value, $violations->get(0)->getCode());
    }

    /**
     * @param class-string<WriteCommand> $commandClass
     */
    #[DataProvider('fallsBackToStoreProvider')]
    #[TestDox('falls back to the committed store for $_dataName')]
    public function testFallsBackToStoreForInBatchCommand(string $commandClass, string $boundLayoutId, string $commandPrimaryKey, bool $touchesLayout): void
    {
        $layout = static::createStub(ContentLayoutEntity::class);
        $layout->method('getLayout')->willReturn([new ContentElement('el-1', 'Sw:Test:RequiresEntity')]);

        $decoder = static::createMock(LayoutTreeDecoder::class);
        $decoder->expects($this->never())->method('decode');

        $gate = new LayoutBindingGate(
            $this->registryReturning($this->searchResult($layout)),
            $this->resolvabilityReporting($this->unresolvedReport()),
            new ViolationConstraintMapper(),
            $decoder,
        );

        $command = $this->layoutCommand($commandClass, ContentLayoutDefinition::ENTITY_NAME, $commandPrimaryKey, $touchesLayout, 'encoded-tree');

        $violations = $gate->bindingViolations($boundLayoutId, [], [$command], Context::createDefaultContext());

        static::assertCount(1, $violations);
    }

    #[TestDox('treats a client-defect in-batch decode as a store fallback without double-reporting')]
    public function testClientDefectInBatchDecodeFallsBackWithoutDoubleReporting(): void
    {
        $layoutId = Uuid::randomHex();

        $decoder = static::createStub(LayoutTreeDecoder::class);
        $decoder->method('decode')->willThrowException(ContentSystemException::invalidFieldValueType('layout', 'array', 'string'));

        $registry = $this->registryReturning($this->searchResult(null));

        $gate = new LayoutBindingGate($registry, $this->resolvabilityReporting($this->unresolvedReport()), new ViolationConstraintMapper(), $decoder);

        $command = $this->layoutCommand(InsertCommand::class, ContentLayoutDefinition::ENTITY_NAME, $layoutId, true, 'not-decodable');

        $violations = $gate->bindingViolations($layoutId, [], [$command], Context::createDefaultContext());

        static::assertCount(0, $violations);
    }

    #[DataProvider('invalidLayoutIdProvider')]
    #[TestDox('returns no violations for $_dataName without touching the store')]
    public function testReturnsNoViolationsForInvalidLayoutId(?string $invalidId): void
    {
        $gate = $this->createDefaultGate();

        static::assertCount(0, $gate->bindingViolations($invalidId, [], [], Context::createDefaultContext()));
    }

    #[TestDox('returns no violations when the bound layout is not (yet) loadable')]
    public function testReturnsNoViolationsWhenLayoutNotFound(): void
    {
        $gate = new LayoutBindingGate(
            $this->registryReturning($this->searchResult(null)),
            static::createStub(LayoutResolvabilityValidator::class),
            new ViolationConstraintMapper(),
            static::createStub(LayoutTreeDecoder::class),
        );

        static::assertCount(0, $gate->bindingViolations(Uuid::randomHex(), [], [], Context::createDefaultContext()));
    }

    #[TestDox('re-throws an in-batch decode error that is not a client defect')]
    public function testRethrowsNonClientDefectInBatchDecodeError(): void
    {
        $layoutId = Uuid::randomHex();

        $decoder = static::createStub(LayoutTreeDecoder::class);
        $decoder->method('decode')->willThrowException(ContentSystemException::invalidFieldType('Expected', 'Actual'));

        $gate = $this->createDefaultGate($decoder);

        $command = $this->layoutCommand(InsertCommand::class, ContentLayoutDefinition::ENTITY_NAME, $layoutId, true, 'internal-fault');

        try {
            $gate->bindingViolations($layoutId, [], [$command], Context::createDefaultContext());
            static::fail('Expected a non-client-defect decode error to propagate.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_FIELD_TYPE, $exception->getErrorCode());
        }
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function invalidLayoutIdProvider(): iterable
    {
        yield 'a non-string (null) layout id' => [null];
        yield 'an empty string layout id' => [''];
    }

    /**
     * @return iterable<string, array{class-string<WriteCommand>}>
     */
    public static function resolvesInBatchTreeFromMatchingCommandProvider(): iterable
    {
        yield 'insert command' => [InsertCommand::class];
        yield 'update command' => [UpdateCommand::class];
    }

    /**
     * @return iterable<string, array{class-string<WriteCommand>, string, string, bool}>
     */
    public static function fallsBackToStoreProvider(): iterable
    {
        $boundLayoutId = Uuid::randomHex();

        yield 'a matching update that leaves the layout column untouched' => [UpdateCommand::class, $boundLayoutId, $boundLayoutId, false];
        yield 'a command whose primary key does not match the bound layout' => [InsertCommand::class, $boundLayoutId, Uuid::randomHex(), true];
        yield 'a delete command for the bound layout' => [DeleteCommand::class, $boundLayoutId, $boundLayoutId, false];
    }

    /**
     * @param class-string<WriteCommand> $commandClass
     */
    private function layoutCommand(string $commandClass, string $entityName, string $primaryKeyHex, bool $touchesLayout, mixed $payloadValue = null): WriteCommand
    {
        $command = static::createStub($commandClass);
        $command->method('getEntityName')->willReturn($entityName);
        $command->method('getDecodedPrimaryKey')->willReturn(['id' => $primaryKeyHex]);
        $command->method('hasField')->willReturnCallback(
            static fn (string $field): bool => $touchesLayout && $field === ContentLayoutDefinition::LAYOUT_FIELD
        );
        $command->method('getPayload')->willReturn(
            $touchesLayout ? [ContentLayoutDefinition::LAYOUT_FIELD => $payloadValue] : []
        );

        return $command;
    }

    private function unresolvedReport(): DiagnosticsReport
    {
        return new DiagnosticsReport([
            new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'target', 'Required property "target" is not deterministically resolvable.'),
        ]);
    }

    private function resolvabilityReporting(DiagnosticsReport $report): LayoutResolvabilityValidator
    {
        $resolvability = static::createStub(LayoutResolvabilityValidator::class);
        $resolvability->method('resolvability')->willReturn($report);

        return $resolvability;
    }

    /**
     * @param EntitySearchResult<EntityCollection<Entity>> $result
     */
    private function registryReturning(EntitySearchResult $result): DefinitionInstanceRegistry
    {
        /** @var StaticEntityRepository<EntityCollection<Entity>> $repository */
        $repository = new StaticEntityRepository([$result]);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        return $registry;
    }

    /**
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    private function searchResult(?ContentLayoutEntity $layout): EntitySearchResult
    {
        $result = static::createStub(EntitySearchResult::class);
        $result->method('first')->willReturn($layout);

        return $result;
    }

    private function createDefaultGate(?LayoutTreeDecoder $decoder = null): LayoutBindingGate
    {
        return new LayoutBindingGate(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(LayoutResolvabilityValidator::class),
            new ViolationConstraintMapper(),
            $decoder ?? static::createStub(LayoutTreeDecoder::class),
        );
    }
}
