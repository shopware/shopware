<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteContext;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutWriteValidator;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\StoredMappingValidator;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutWriteValidator::class)]
class ContentLayoutWriteValidatorTest extends TestCase
{
    private const STORED_LAYOUT = '[{"id":"el-1","component":"Sw:Block"}]';

    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [ContentLayoutDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    #[TestDox('accepts a resolvable creation against a registered root source')]
    public function testAcceptsResolvableCreation(): void
    {
        $gate = static::createStub(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->method('resolvability')->willReturn(new DiagnosticsReport([]));

        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn(['none']);
        $registry->method('resolve')->willReturn([]);

        $validator = $this->validator($gate, $registry);

        $event = $this->event([$this->layoutCreate(['layout' => self::STORED_LAYOUT, 'root_source' => 'none'])]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestDox('resolves a layout-only edit against the stored root source of the write outcome')]
    public function testLayoutOnlyEditResolvesAgainstStoredRootSource(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->expects($this->once())->method('resolvability')
            ->with(static::anything(), static::anything(), 'category')
            ->willReturn(new DiagnosticsReport([]));

        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn(['category', 'none']);
        $registry->method('resolve')->willReturn([]);

        $command = $this->layoutUpdate(['layout' => self::STORED_LAYOUT]);
        $validator = $this->validator($gate, $registry, $this->storedRootSource($command, 'category'));

        $event = $this->event([$command]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * The resulting tree is remembered first, so a lookup by anything other than the record's resulting layout
     * value (the tree remembered last, say) gates the overwritten tree instead.
     */
    #[TestDox('gates a layout written twice in one batch once, on the tree behind its resulting layout value')]
    public function testLayoutWrittenTwiceInOneBatchIsGatedOnTheTreeBehindItsResultingValue(): void
    {
        $id = Uuid::randomBytes();
        $create = $this->layoutCreate(['layout' => '[{"id":"el-first"}]', 'root_source' => 'product'], $id);
        $edit = $this->layoutUpdate(['layout' => '[{"id":"el-resulting"}]'], $id);
        $resultingTree = new StoredTree([new StoredElement('el-resulting', 'Sw:Block')]);

        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->expects($this->once())->method('resolvability')
            ->with($resultingTree->roots, static::anything(), 'product')
            ->willReturn(new DiagnosticsReport([new Violation(ViolationCode::UnresolvedRequired, 'el-resulting', 'target', 'unresolved')]));

        $write = WriteContext::createFromContext(Context::createDefaultContext());
        $memo = new LayoutWriteContext();
        $memo->remember($write, '[{"id":"el-resulting"}]', $resultingTree);
        $memo->remember($write, '[{"id":"el-first"}]', new StoredTree([new StoredElement('el-first', 'Sw:Block')]));
        $write->getContext()->addExtension(LayoutWriteContext::EXTENSION_NAME, $memo);

        $event = new PreWriteValidationEvent($write, [$create, $edit]);
        $this->validator($gate)->preValidate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
        static::assertSame('/update', $exceptions[0]->getPath());
    }

    #[TestDox('skips resolvability for a layout-only edit whose root source the write outcome cannot resolve')]
    public function testLayoutOnlyEditWithoutAnyRootSourceSkipsResolvability(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->expects($this->never())->method('resolvability');

        $command = $this->layoutUpdate(['layout' => self::STORED_LAYOUT]);
        $validator = $this->validator($gate, null, $this->storedRootSource($command, null));

        $event = $this->event([$command]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestDox('bypasses every check when skip flag is set')]
    public function testSkipFlagBypassesValidation(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->expects($this->never())->method('wellFormedness');
        $gate->expects($this->never())->method('resolvability');

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->expects($this->never())->method('knownRootSources');
        $registry->expects($this->never())->method('resolve');

        $validator = $this->validator($gate, $registry);

        $command = $this->layoutCreate(['layout' => self::STORED_LAYOUT, 'root_source' => 'bogus']);
        $write = $this->writeWithMemoFor([$command]);
        $context = $write->getContext();
        $context->addState(LayoutGate::SKIP_VALIDATION_STATE);

        $event = new PreWriteValidationEvent($write, [$command]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * The DAL dispatches the validation event again when it retries a write's transaction, without encoding the
     * layout again, so the second dispatch must find the tree the first one judged.
     */
    #[TestDox('reaches the same verdict when the validation event of one write is dispatched again')]
    public function testARepeatedValidationEventIsJudgedAgainOnTheSameTree(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->expects($this->exactly(2))->method('resolvability')
            ->willReturn(new DiagnosticsReport([new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'target', 'unresolved')]));

        $validator = $this->validator($gate, $this->registryKnowing(['product']));

        $command = $this->layoutCreate(['layout' => self::STORED_LAYOUT, 'root_source' => 'product']);
        $write = $this->writeWithMemoFor([$command]);

        $validator->preValidate(new PreWriteValidationEvent($write, [$command]));
        $write->resetExceptions();

        $retry = new PreWriteValidationEvent($write, [$command]);
        $validator->preValidate($retry);

        $violation = $this->onlyViolation($retry);
        static::assertSame(ViolationCode::UnresolvedRequired->value, $violation->getCode());
    }

    #[TestDox('does not gate the tree of a layout the batch creates and then deletes')]
    public function testTreeWrittenBeforeTheRecordsDeleteIsNotGated(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->expects($this->never())->method('wellFormedness');

        $create = $this->layoutCreate(['layout' => self::STORED_LAYOUT, 'root_source' => 'none']);
        $delete = new DeleteCommand(
            $this->registry->getByEntityName(ContentLayoutDefinition::ENTITY_NAME),
            $create->getPrimaryKey(),
            static::createStub(EntityExistence::class),
        );
        $write = $this->writeWithMemoFor([$create]);

        $event = new PreWriteValidationEvent($write, [$create, $delete]);
        $this->validator($gate)->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestDox('runs the membership check only for a root-source-only command, looking up no memo entry')]
    public function testRootSourceOnlyCommandRunsMembershipOnly(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->expects($this->never())->method('wellFormedness');
        $gate->expects($this->never())->method('resolvability');

        $validator = $this->validator($gate, $this->registryKnowing(['product']));

        // No memo entry exists for this command; the gate must not look for one, or it would fail hard.
        $command = $this->layoutCreate(['root_source' => 'product']);
        $write = WriteContext::createFromContext(Context::createDefaultContext());
        $write->getContext()->addExtension(LayoutWriteContext::EXTENSION_NAME, new LayoutWriteContext());

        $event = new PreWriteValidationEvent($write, [$command]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestDox('rejects an unregistered root source on a root-source-only command with unknownRootSource and never reaches resolve')]
    public function testRootSourceOnlyCommandWithUnregisteredRootSourceIsRejected(): void
    {
        $validator = $this->validatorRejectingBeforeResolvability();

        $write = WriteContext::createFromContext(Context::createDefaultContext());
        $write->getContext()->addExtension(LayoutWriteContext::EXTENSION_NAME, new LayoutWriteContext());

        $event = new PreWriteValidationEvent($write, [$this->layoutCreate(['root_source' => 'bogus'])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $violation->getCode());
    }

    #[TestDox('runs resolvability against the declared root source on creation and records a binding error')]
    public function testKnownRootSourceRunsResolvabilityOnCreation(): void
    {
        $gate = static::createStub(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->method('resolvability')
            ->willReturn(new DiagnosticsReport([new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'target', 'unresolved')]));

        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn(['product']);
        $registry->method('resolve')->willReturn([]);

        $validator = $this->validator($gate, $registry);

        $event = $this->event([$this->layoutCreate(['layout' => self::STORED_LAYOUT, 'root_source' => 'product'])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ViolationCode::UnresolvedRequired->value, $violation->getCode());
    }

    #[TestDox('fails hard when a command writes the layout column but no tree was memoized for it')]
    public function testLayoutCommandWithoutAMemoEntryFailsHard(): void
    {
        $validator = $this->validator();

        $command = $this->layoutCreate(['layout' => self::STORED_LAYOUT, 'root_source' => 'none']);
        $context = Context::createDefaultContext();

        $this->expectExceptionObject(
            ContentSystemException::layoutWriteMemoMissing(ContentLayoutDefinition::ENTITY_NAME, '/insert')
        );

        $validator->preValidate(new PreWriteValidationEvent(WriteContext::createFromContext($context), [$command]));
    }

    #[TestDox('fails hard when the write memoized trees but none for the layout value the record ends up with')]
    public function testResultingLayoutValueWithoutAMemoEntryFailsHard(): void
    {
        $validator = $this->validator();

        $command = $this->layoutCreate(['layout' => self::STORED_LAYOUT, 'root_source' => 'none']);
        $write = WriteContext::createFromContext(Context::createDefaultContext());
        $memo = new LayoutWriteContext();
        $memo->remember($write, '[{"id":"el-other"}]', $this->tree());
        $write->getContext()->addExtension(LayoutWriteContext::EXTENSION_NAME, $memo);

        $this->expectExceptionObject(
            ContentSystemException::layoutWriteMemoMissing(ContentLayoutDefinition::ENTITY_NAME, '/insert')
        );

        $validator->preValidate(new PreWriteValidationEvent($write, [$command]));
    }

    #[TestDox('fails hard when only another write on the same context memoized a tree for the layout value')]
    public function testEntryOfAnotherWriteOnTheSameContextCountsAsAbsent(): void
    {
        $validator = $this->validator();

        $command = $this->layoutCreate(['layout' => self::STORED_LAYOUT, 'root_source' => 'none']);
        $otherWrite = $this->writeWithMemoFor([$command]);
        $write = WriteContext::createFromContext($otherWrite->getContext());

        $this->expectExceptionObject(
            ContentSystemException::layoutWriteMemoMissing(ContentLayoutDefinition::ENTITY_NAME, '/insert')
        );

        $validator->preValidate(new PreWriteValidationEvent($write, [$command]));
    }

    #[TestDox('rejects an unregistered root source on creation with unknownRootSource and never reaches resolve')]
    public function testUnknownRootSourceIsRejectedAndResolvabilitySkipped(): void
    {
        $validator = $this->validatorRejectingBeforeResolvability();

        $event = $this->event([$this->layoutCreate(['layout' => self::STORED_LAYOUT, 'root_source' => 'bogus'])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $violation->getCode());
    }

    #[DataProvider('rejectsNonStringRootSourceOnCreationProvider')]
    #[TestDox('rejects a non-string root source ($debugType) on creation with unknownRootSource and skips resolvability')]
    public function testNonStringRootSourceIsRejectedOnCreation(mixed $rootSource, string $debugType): void
    {
        $validator = $this->validatorRejectingBeforeResolvability();

        $event = $this->event([$this->layoutCreate(['layout' => self::STORED_LAYOUT, 'root_source' => $rootSource])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $violation->getCode());
        static::assertStringContainsString('"' . $debugType . '"', (string) $violation->getMessage());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function rejectsNonStringRootSourceOnCreationProvider(): iterable
    {
        yield 'null' => [null, 'null'];
        yield 'array' => [[], 'array'];
        yield 'int' => [42, 'int'];
    }

    #[TestDox('rejects a layout-only edit whose committed root source is no longer registered, skipping resolvability')]
    public function testLayoutOnlyEditWithDeregisteredCommittedRootSourceIsRejected(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->expects($this->never())->method('resolvability');

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn(['product', 'none']);
        $registry->expects($this->never())->method('resolve');

        $command = $this->layoutUpdate(['layout' => self::STORED_LAYOUT]);
        $validator = $this->validator($gate, $registry, $this->storedRootSource($command, 'category'));

        $event = $this->event([$command]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $violation->getCode());
    }

    private function validator(
        ?LayoutGate $gate = null,
        ?RootSourceRegistry $registry = null,
        ?WriteOutcome $writeOutcome = null,
    ): ContentLayoutWriteValidator {
        return new ContentLayoutWriteValidator(
            $gate ?? $this->cleanGate(),
            new ViolationConstraintMapper(),
            $registry ?? $this->registryKnowing(['product', 'category', 'none']),
            $writeOutcome ?? new WriteOutcome(static::createStub(RowLoader::class)),
            $this->cleanMappingValidator(),
        );
    }

    /**
     * A write outcome over a database that stores the command's layout with the given root source.
     */
    private function storedRootSource(WriteCommand $command, ?string $rootSource): WriteOutcome
    {
        $rowLoader = static::createStub(RowLoader::class);
        $rowLoader->method('load')->willReturn([
            [...$command->getPrimaryKey(), ContentLayoutDefinition::ROOT_SOURCE_FIELD => $rootSource],
        ]);

        return new WriteOutcome($rowLoader);
    }

    /**
     * Builds a validator whose gate and registry assert that resolvability is never reached: a creation whose
     * root_source is unregistered (or not a string) must be rejected on membership before resolve() runs.
     */
    private function validatorRejectingBeforeResolvability(): ContentLayoutWriteValidator
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->expects($this->never())->method('resolvability');

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn(['product', 'none']);
        $registry->expects($this->never())->method('resolve');

        return $this->validator($gate, $registry);
    }

    private function cleanGate(): LayoutGate
    {
        $gate = static::createStub(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->method('resolvability')->willReturn(new DiagnosticsReport([]));

        return $gate;
    }

    private function cleanMappingValidator(): StoredMappingValidator
    {
        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturn(false);

        $candidateRegistry = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidateRegistry->method('forRootSource')->willReturn([]);

        return new StoredMappingValidator(new StoredMappingInspector(
            $typeRegistry,
            $candidateRegistry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        ));
    }

    /**
     * @param list<string> $known
     */
    private function registryKnowing(array $known): RootSourceRegistry
    {
        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn($known);
        $registry->method('resolve')->willReturn([]);

        return $registry;
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function event(array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent($this->writeWithMemoFor($commands), $commands);
    }

    /**
     * A write carrying what the layout field serializer would have left on its Context: a memoized tree for
     * each layout value the commands write. The entries belong to the returned WriteContext, so the event must
     * be built from this instance and not from a second one over the same Context.
     *
     * @param list<WriteCommand> $commands
     */
    private function writeWithMemoFor(array $commands): WriteContext
    {
        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());
        $memo = new LayoutWriteContext();

        foreach ($commands as $command) {
            $encoded = $command->getPayload()[ContentLayoutDefinition::LAYOUT_FIELD] ?? null;

            if (!\is_string($encoded)) {
                continue;
            }

            $memo->remember($writeContext, $encoded, $this->tree());
        }

        $writeContext->getContext()->addExtension(LayoutWriteContext::EXTENSION_NAME, $memo);

        return $writeContext;
    }

    private function tree(): StoredTree
    {
        return new StoredTree([new StoredElement('el-1', 'Sw:Block')]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function layoutCreate(array $payload, ?string $id = null): InsertCommand
    {
        $id ??= Uuid::randomBytes();

        return new InsertCommand(
            $this->registry->getByEntityName(ContentLayoutDefinition::ENTITY_NAME),
            ['id' => $id, ...$payload],
            ['id' => $id],
            static::createStub(EntityExistence::class),
            '/insert',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function layoutUpdate(array $payload, ?string $id = null): UpdateCommand
    {
        $id ??= Uuid::randomBytes();

        return new UpdateCommand(
            $this->registry->getByEntityName(ContentLayoutDefinition::ENTITY_NAME),
            ['id' => $id, ...$payload],
            ['id' => $id],
            static::createStub(EntityExistence::class),
            '/update',
        );
    }

    private function onlyViolation(PreWriteValidationEvent $event): ConstraintViolationInterface
    {
        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);

        $exception = $exceptions[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);

        $violations = $exception->getViolations();
        static::assertCount(1, $violations);

        return $violations->get(0);
    }
}
