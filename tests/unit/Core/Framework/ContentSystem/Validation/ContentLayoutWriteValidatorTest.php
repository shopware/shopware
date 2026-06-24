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
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutWriteValidator;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutTreeDecoder;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
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

    #[TestDox('records an invalid_config violation when the layout tree is an undecodable client defect')]
    public function testRecordsInvalidConfigViolationWhenLayoutTreeIsUndecodableClientDefect(): void
    {
        $expected = ContentSystemException::invalidFieldValueType('layout', 'array', 'string');

        $decoder = static::createStub(LayoutTreeDecoder::class);
        $decoder->method('decode')->willThrowException($expected);

        $gate = $this->createMock(LayoutGate::class);
        $gate->expects($this->never())->method('wellFormedness');
        $gate->expects($this->never())->method('resolvability');

        $validator = $this->validator($gate, decoder: $decoder);

        $event = $this->event([$this->layoutUpdate(['layout' => 'not-decodable'])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ViolationCode::InvalidConfig->value, $violation->getCode());
        static::assertSame($expected->getMessage(), $violation->getMessage());
    }

    #[TestDox('rethrows a non-client-defect decode failure unchanged')]
    public function testRethrowsNonClientDefectDecodeFailureUnchanged(): void
    {
        $expected = ContentSystemException::invalidMapKey('someMap', 'int');

        $decoder = static::createStub(LayoutTreeDecoder::class);
        $decoder->method('decode')->willThrowException($expected);

        $validator = $this->validator(decoder: $decoder);

        $this->expectExceptionObject($expected);

        $validator->preValidate($this->event([$this->layoutUpdate(['layout' => 'not-decodable'])]));
    }

    #[TestDox('bypasses every check when the write context carries the skip flag')]
    public function testSkipFlagBypassesValidation(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->expects($this->never())->method('wellFormedness');
        $gate->expects($this->never())->method('resolvability');

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->expects($this->never())->method('knownRootSources');
        $registry->expects($this->never())->method('resolve');

        $validator = $this->validator($gate, $registry);

        $context = Context::createDefaultContext();
        $context->addState(LayoutGate::SKIP_VALIDATION_STATE);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [$this->layoutCreate(['layout' => [], 'root_source' => 'bogus'])],
        );

        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestDox('rejects an unregistered root source on creation with unknownRootSource and never reaches resolve')]
    public function testUnknownRootSourceIsRejectedAndResolvabilitySkipped(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->expects($this->never())->method('resolvability');

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn(['product', 'none']);
        $registry->expects($this->never())->method('resolve');

        $validator = $this->validator($gate, $registry);

        $event = $this->event([$this->layoutCreate(['layout' => [], 'root_source' => 'bogus'])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $violation->getCode());
    }

    #[DataProvider('nonStringRootSourceProvider')]
    #[TestDox('rejects a non-string root source ($debugType) on creation with unknownRootSource and skips resolvability')]
    public function testNonStringRootSourceIsRejectedOnCreation(mixed $rootSource, string $debugType): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->expects($this->never())->method('resolvability');

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn(['product', 'none']);
        $registry->expects($this->never())->method('resolve');

        $validator = $this->validator($gate, $registry);

        $event = $this->event([$this->layoutCreate(['layout' => [], 'root_source' => $rootSource])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $violation->getCode());
        static::assertStringContainsString('"' . $debugType . '"', (string) $violation->getMessage());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function nonStringRootSourceProvider(): iterable
    {
        yield 'null' => [null, 'null'];
        yield 'array' => [[], 'array'];
        yield 'int' => [42, 'int'];
    }

    #[TestDox('runs resolvability against the declared root source on creation and records a binding error')]
    public function testKnownRootSourceRunsResolvabilityOnCreation(): void
    {
        $rootContext = [];

        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->expects($this->once())
            ->method('resolvability')
            ->willReturn(new DiagnosticsReport([new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'target', 'unresolved')]));

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn(['product']);
        $registry->expects($this->once())->method('resolve')->with('product')->willReturn($rootContext);

        $validator = $this->validator($gate, $registry);

        $event = $this->event([$this->layoutCreate(['layout' => [], 'root_source' => 'product'])]);
        $validator->preValidate($event);

        $violation = $this->onlyViolation($event);
        static::assertSame(ViolationCode::UnresolvedRequired->value, $violation->getCode());
    }

    #[TestDox('accepts a resolvable creation against a registered root source')]
    public function testAcceptsResolvableCreation(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->method('resolvability')->willReturn(new DiagnosticsReport([]));

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->method('knownRootSources')->willReturn(['none']);
        $registry->method('resolve')->with('none')->willReturn([]);

        $validator = $this->validator($gate, $registry);

        $event = $this->event([$this->layoutCreate(['layout' => [], 'root_source' => 'none'])]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestDox('re-validates a layout-only edit against the committed root source read by the reader after gating its membership')]
    public function testLayoutOnlyEditResolvesAgainstCommittedRootSource(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->method('resolvability')->willReturn(new DiagnosticsReport([]));

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->expects($this->once())->method('knownRootSources')->willReturn(['category', 'none']);
        $registry->expects($this->once())->method('resolve')->with('category')->willReturn([]);

        $reader = $this->createMock(LayoutRootSourceReader::class);
        $reader->expects($this->once())->method('read')->willReturn('category');

        $validator = $this->validator($gate, $registry, $reader);

        $event = $this->event([$this->layoutUpdate(['layout' => []])]);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestDox('rejects a layout-only edit whose committed root source is no longer registered, skipping resolvability')]
    public function testLayoutOnlyEditWithDeregisteredCommittedRootSourceIsRejected(): void
    {
        $gate = $this->createMock(LayoutGate::class);
        $gate->method('wellFormedness')->willReturn(new DiagnosticsReport([]));
        $gate->expects($this->never())->method('resolvability');

        $registry = $this->createMock(RootSourceRegistry::class);
        $registry->expects($this->once())->method('knownRootSources')->willReturn(['product', 'none']);
        $registry->expects($this->never())->method('resolve');

        $reader = $this->createMock(LayoutRootSourceReader::class);
        $reader->expects($this->once())->method('read')->willReturn('category');

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
        ?LayoutTreeDecoder $decoder = null,
    ): ContentLayoutWriteValidator {
        $decoder ??= $this->decoderReturning([new ContentElement('el-1', 'Sw:Block')]);

        return new ContentLayoutWriteValidator(
            $gate ?? $this->cleanGate(),
            new ViolationConstraintMapper(),
            $decoder,
            $registry ?? $this->registryKnowing(['product', 'category', 'none']),
            $reader ?? static::createStub(LayoutRootSourceReader::class),
        );
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
     * @param list<ContentElement> $tree
     */
    private function decoderReturning(array $tree): LayoutTreeDecoder
    {
        $decoder = static::createStub(LayoutTreeDecoder::class);
        $decoder->method('decode')->willReturn($tree);

        return $decoder;
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function event(array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), $commands);
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
