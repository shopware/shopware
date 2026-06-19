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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(LayoutBindingGate::class)]
class LayoutBindingGateTest extends TestCase
{
    #[TestDox('returns no violations for a non-string layout id without touching the store')]
    public function testReturnsNoViolationsForNonStringLayoutId(): void
    {
        $gate = new LayoutBindingGate(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(LayoutResolvabilityValidator::class),
            new ViolationConstraintMapper(),
            static::createStub(LayoutTreeDecoder::class),
        );

        static::assertCount(0, $gate->bindingViolations(null, [], [], Context::createDefaultContext()));
    }

    #[TestDox('returns no violations for an empty layout id without touching the store')]
    public function testReturnsNoViolationsForEmptyLayoutId(): void
    {
        $gate = new LayoutBindingGate(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(LayoutResolvabilityValidator::class),
            new ViolationConstraintMapper(),
            static::createStub(LayoutTreeDecoder::class),
        );

        static::assertCount(0, $gate->bindingViolations('', [], [], Context::createDefaultContext()));
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

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === [$hex] ? $this->searchResult($layout) : $this->searchResult(null)
        );
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $gate = new LayoutBindingGate($registry, $this->resolvabilityReporting($this->unresolvedReport()), new ViolationConstraintMapper(), static::createStub(LayoutTreeDecoder::class));

        $violations = $gate->bindingViolations(Uuid::fromHexToBytes($hex), [], [], Context::createDefaultContext());

        static::assertCount(1, $violations);
    }

    /**
     * @param class-string<WriteCommand> $commandClass
     */
    #[DataProvider('matchingLayoutCommandProvider')]
    #[TestDox('resolves the in-batch tree from a matching $_dataName carrying the layout column and skips the store')]
    public function testResolvesInBatchTreeFromMatchingCommand(string $commandClass): void
    {
        $layoutId = Uuid::randomHex();

        $decoder = static::createMock(LayoutTreeDecoder::class);
        $decoder->method('decode')->willReturn([new ContentElement('el-1', 'Sw:Test:RequiresEntity')]);

        $registry = static::createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $gate = new LayoutBindingGate($registry, $this->resolvabilityReporting($this->unresolvedReport()), new ViolationConstraintMapper(), $decoder);

        $command = $this->layoutCommand($commandClass, ContentLayoutDefinition::ENTITY_NAME, $layoutId, true, 'encoded-tree');

        $violations = $gate->bindingViolations($layoutId, [], [$command], Context::createDefaultContext());

        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::UnresolvedRequired->value, $violations->get(0)->getCode());
    }

    #[TestDox('falls back to the committed store when a matching update does not touch the layout column')]
    public function testFallsBackToStoreWhenUpdateLeavesLayoutUntouched(): void
    {
        $layoutId = Uuid::randomHex();

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

        $command = $this->layoutCommand(UpdateCommand::class, ContentLayoutDefinition::ENTITY_NAME, $layoutId, false);

        $violations = $gate->bindingViolations($layoutId, [], [$command], Context::createDefaultContext());

        static::assertCount(1, $violations);
    }

    #[TestDox('falls back to the committed store for a command whose primary key does not match the bound layout')]
    public function testFallsBackToStoreForNonMatchingCommandId(): void
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

        $command = $this->layoutCommand(InsertCommand::class, ContentLayoutDefinition::ENTITY_NAME, Uuid::randomHex(), true, 'encoded-tree');

        $violations = $gate->bindingViolations(Uuid::randomHex(), [], [$command], Context::createDefaultContext());

        static::assertCount(1, $violations);
    }

    #[TestDox('skips a delete command for the bound layout and falls back to the committed store')]
    public function testSkipsDeleteCommandAndFallsBackToStore(): void
    {
        $layoutId = Uuid::randomHex();

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

        $command = $this->layoutCommand(DeleteCommand::class, ContentLayoutDefinition::ENTITY_NAME, $layoutId, false);

        $violations = $gate->bindingViolations($layoutId, [], [$command], Context::createDefaultContext());

        static::assertCount(1, $violations);
    }

    #[TestDox('treats a client-defect in-batch decode as a store fallback without double-reporting')]
    public function testClientDefectInBatchDecodeFallsBackWithoutDoubleReporting(): void
    {
        $layoutId = Uuid::randomHex();

        $decoder = static::createMock(LayoutTreeDecoder::class);
        $decoder->method('decode')->willThrowException(ContentSystemException::invalidFieldValueType('layout', 'array', 'string'));

        $registry = $this->registryReturning($this->searchResult(null));

        $gate = new LayoutBindingGate($registry, $this->resolvabilityReporting($this->unresolvedReport()), new ViolationConstraintMapper(), $decoder);

        $command = $this->layoutCommand(InsertCommand::class, ContentLayoutDefinition::ENTITY_NAME, $layoutId, true, 'not-decodable');

        $violations = $gate->bindingViolations($layoutId, [], [$command], Context::createDefaultContext());

        static::assertCount(0, $violations);
    }

    #[TestDox('re-throws an in-batch decode error that is not a client defect')]
    public function testRethrowsNonClientDefectInBatchDecodeError(): void
    {
        $layoutId = Uuid::randomHex();

        $decoder = static::createMock(LayoutTreeDecoder::class);
        $decoder->method('decode')->willThrowException(ContentSystemException::invalidFieldType('Expected', 'Actual'));

        $gate = new LayoutBindingGate(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(LayoutResolvabilityValidator::class),
            new ViolationConstraintMapper(),
            $decoder,
        );

        $command = $this->layoutCommand(InsertCommand::class, ContentLayoutDefinition::ENTITY_NAME, $layoutId, true, 'internal-fault');

        try {
            $gate->bindingViolations($layoutId, [], [$command], Context::createDefaultContext());
            static::fail('Expected a non-client-defect decode error to propagate.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_FIELD_TYPE, $exception->getErrorCode());
        }
    }

    /**
     * @return iterable<string, array{class-string<WriteCommand>}>
     */
    public static function matchingLayoutCommandProvider(): iterable
    {
        yield 'insert command' => [InsertCommand::class];
        yield 'update command' => [UpdateCommand::class];
    }

    /**
     * @param class-string<WriteCommand> $commandClass
     */
    private function layoutCommand(string $commandClass, string $entityName, string $primaryKeyHex, bool $touchesLayout, mixed $payloadValue = null): WriteCommand
    {
        $command = static::createMock($commandClass);
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
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

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
}
