<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\DalWriteInstrumentor;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DalWriteInstrumentor::class)]
class DalWriteInstrumentorTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function testMeasureReturnsCallbackResultAndEmitsDurationWithResolvedLabels(): void
    {
        $returned = $this->createInstrumentor()->measure(
            DalWriteInstrumentor::OPERATION_WRITE,
            $this->definition('product_price'),
            fn (): string => 'callback-result',
        );

        static::assertSame('callback-result', $returned);

        static::assertCount(1, $this->emitted);
        $metric = $this->emitted[0];
        static::assertSame('dal.write.duration', $metric->name);
        static::assertIsFloat($metric->value);
        static::assertGreaterThanOrEqual(0.0, $metric->value);

        // the entity name is forwarded to the resolver and the resolved group lands in the label
        static::assertSame('group:product_price', $metric->labels['entity_group']);
        static::assertSame('write', $metric->labels['operation']);
        static::assertSame('success', $metric->labels['result']);
    }

    /**
     * @param DalWriteInstrumentor::OPERATION_* $operation
     */
    #[DataProvider('operationProvider')]
    public function testOperationConstantIsPassedThroughAsLabel(string $operation): void
    {
        $this->createInstrumentor()->measure(
            $operation,
            $this->definition('product'),
            fn (): null => null,
        );

        static::assertSame($operation, $this->emitted[0]->labels['operation']);
    }

    /**
     * @return \Generator<string, array{0: DalWriteInstrumentor::OPERATION_*}>
     */
    public static function operationProvider(): \Generator
    {
        yield 'write' => [DalWriteInstrumentor::OPERATION_WRITE];
        yield 'delete' => [DalWriteInstrumentor::OPERATION_DELETE];
    }

    public function testThrowingCallbackIsRethrownAndDurationRecordedAsFailed(): void
    {
        // the rethrow is asserted via expectExceptionObject; the emit assertions run in finally
        $this->expectExceptionObject(new \RuntimeException('boom'));

        try {
            $this->createInstrumentor()->measure(
                DalWriteInstrumentor::OPERATION_DELETE,
                $this->definition('product'),
                function (): void {
                    throw new \RuntimeException('boom');
                },
            );
        } finally {
            static::assertCount(1, $this->emitted);
            static::assertSame('failed', $this->emitted[0]->labels['result']);
        }
    }

    public function testCallbackIsInvokedExactlyOnce(): void
    {
        $calls = 0;

        $this->createInstrumentor()->measure(
            DalWriteInstrumentor::OPERATION_WRITE,
            $this->definition('product'),
            function () use (&$calls): void {
                ++$calls;
            },
        );

        static::assertSame(1, $calls);
    }

    private function createInstrumentor(): DalWriteInstrumentor
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        // echo the entity name back, so tests can assert the forwarding without coupling to the real resolver
        $groupResolver = static::createStub(EntityGroupResolver::class);
        $groupResolver->method('resolve')->willReturnCallback(static fn (string $entityName): string => 'group:' . $entityName);

        return new DalWriteInstrumentor($meter, $groupResolver);
    }

    private function definition(string $entityName): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        return $definition;
    }
}
