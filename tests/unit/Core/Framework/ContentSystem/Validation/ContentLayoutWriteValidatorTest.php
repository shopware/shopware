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
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutWriteValidator;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
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

        $event = $this->event([$this->layoutCreate(['layout' => [], 'root_source' => 'none'])]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestDox('re-validates a layout-only edit against the committed root source read by the reader after gating its membership')]
    public function testLayoutOnlyEditResolvesAgainstCommittedRootSource(): void
    {
        $gate = static::createStub(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->method('resolvability')->willReturn(new DiagnosticsReport([]));

        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn(['category', 'none']);
        $registry->method('resolve')->willReturn([]);

        $reader = static::createStub(LayoutRootSourceReader::class);
        $reader->method('read')->willReturn('category');

        $validator = $this->validator($gate, $registry, $reader);

        $event = $this->event([$this->layoutUpdate(['layout' => []])]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestDox('bypasses every check and drains memo when skip flag is set')]
    public function testSkipFlagBypassesValidationAndDrainsMemo(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->expects($this->never())->method('wellFormedness');
        $gate->expects($this->never())->method('resolvability');

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->expects($this->never())->method('knownRootSources');
        $registry->expects($this->never())->method('resolve');

        $validator = $this->validator($gate, $registry);

        $command = $this->layoutCreate(['layout' => [], 'root_source' => 'bogus']);
        $context = $this->contextWithMemoFor([$command]);
        $context->addState(LayoutGate::SKIP_VALIDATION_STATE);

        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), [$command]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
        static::assertTrue($this->memoOf($context)->isEmpty());
    }

    #[TestDox('leaves nothing memoized behind after a validated write')]
    public function testValidatedWriteEmptiesTheMemo(): void
    {
        $validator = $this->validator();

        $command = $this->layoutCreate(['layout' => [], 'root_source' => 'none']);
        $context = $this->contextWithMemoFor([$command]);

        $validator->preValidate(new PreWriteValidationEvent(WriteContext::createFromContext($context), [$command]));

        static::assertTrue($this->memoOf($context)->isEmpty());
    }

    #[TestDox('leaves the memo untouched for a command that writes neither the layout nor the root source')]
    public function testCommandTouchingNeitherFieldConsumesNothing(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->expects($this->never())->method('wellFormedness');

        $validator = $this->validator($gate);

        $command = $this->layoutUpdate(['name' => 'renamed']);
        $context = Context::createDefaultContext();
        $memo = new LayoutWriteContext();
        $memo->remember($command->getEntityName(), $command->getDecodedPrimaryKey()['id'], $this->tree());
        $context->addExtension(LayoutWriteContext::EXTENSION_NAME, $memo);

        $validator->preValidate(new PreWriteValidationEvent(WriteContext::createFromContext($context), [$command]));

        static::assertFalse($memo->isEmpty());
    }

    #[TestDox('runs the membership check only for a root-source-only command, consuming no memo entry')]
    public function testRootSourceOnlyCommandRunsMembershipOnly(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->expects($this->never())->method('wellFormedness');
        $gate->expects($this->never())->method('resolvability');

        $validator = $this->validator($gate, $this->registryKnowing(['product']));

        // No memo entry exists for this command; the gate must not look for one, or it would fail hard.
        $command = $this->layoutCreate(['root_source' => 'product']);
        $context = Context::createDefaultContext();
        $context->addExtension(LayoutWriteContext::EXTENSION_NAME, new LayoutWriteContext());

        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), [$command]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
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

        $event = $this->event([$this->layoutCreate(['layout' => [], 'root_source' => 'product'])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ViolationCode::UnresolvedRequired->value, $violation->getCode());
    }

    #[TestDox('fails hard when a command writes the layout column but no tree was memoized for it')]
    public function testLayoutCommandWithoutAMemoEntryFailsHard(): void
    {
        $validator = $this->validator();

        $command = $this->layoutCreate(['layout' => [], 'root_source' => 'none']);
        $context = Context::createDefaultContext();

        $this->expectExceptionObject(
            ContentSystemException::layoutWriteMemoMissing(ContentLayoutDefinition::ENTITY_NAME, '/insert')
        );

        $validator->preValidate(new PreWriteValidationEvent(WriteContext::createFromContext($context), [$command]));
    }

    #[TestDox('rejects an unregistered root source on creation with unknownRootSource and never reaches resolve')]
    public function testUnknownRootSourceIsRejectedAndResolvabilitySkipped(): void
    {
        $validator = $this->validatorRejectingBeforeResolvability();

        $event = $this->event([$this->layoutCreate(['layout' => [], 'root_source' => 'bogus'])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $violation->getCode());
    }

    #[DataProvider('rejectsNonStringRootSourceOnCreationProvider')]
    #[TestDox('rejects a non-string root source ($debugType) on creation with unknownRootSource and skips resolvability')]
    public function testNonStringRootSourceIsRejectedOnCreation(mixed $rootSource, string $debugType): void
    {
        $validator = $this->validatorRejectingBeforeResolvability();

        $event = $this->event([$this->layoutCreate(['layout' => [], 'root_source' => $rootSource])]);
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

        $reader = static::createStub(LayoutRootSourceReader::class);
        $reader->method('read')->willReturn('category');

        $validator = $this->validator($gate, $registry, $reader);

        $event = $this->event([$this->layoutUpdate(['layout' => []])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $violation->getCode());
    }

    private function validator(
        ?LayoutGate $gate = null,
        ?RootSourceRegistry $registry = null,
        ?LayoutRootSourceReader $reader = null,
    ): ContentLayoutWriteValidator {
        return new ContentLayoutWriteValidator(
            $gate ?? $this->cleanGate(),
            new ViolationConstraintMapper(),
            $registry ?? $this->registryKnowing(['product', 'category', 'none']),
            $reader ?? static::createStub(LayoutRootSourceReader::class),
        );
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
        return new PreWriteValidationEvent(
            WriteContext::createFromContext($this->contextWithMemoFor($commands)),
            $commands
        );
    }

    /**
     * A context carrying what the layout field serializer would have left on it: one memoized tree per command
     * that writes the layout column.
     *
     * @param list<WriteCommand> $commands
     */
    private function contextWithMemoFor(array $commands): Context
    {
        $context = Context::createDefaultContext();
        $memo = new LayoutWriteContext();

        foreach ($commands as $command) {
            if (!$command->hasField(ContentLayoutDefinition::LAYOUT_FIELD)) {
                continue;
            }

            $memo->remember($command->getEntityName(), $command->getDecodedPrimaryKey()['id'], $this->tree());
        }

        $context->addExtension(LayoutWriteContext::EXTENSION_NAME, $memo);

        return $context;
    }

    private function memoOf(Context $context): LayoutWriteContext
    {
        $memo = $context->getExtension(LayoutWriteContext::EXTENSION_NAME);
        static::assertInstanceOf(LayoutWriteContext::class, $memo);

        return $memo;
    }

    private function tree(): StoredTree
    {
        return new StoredTree([new StoredElement('el-1', 'Sw:Block')]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function layoutCreate(array $payload): InsertCommand
    {
        $id = Uuid::randomBytes();

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
    private function layoutUpdate(array $payload): UpdateCommand
    {
        $id = Uuid::randomBytes();

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
