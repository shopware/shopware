<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\ValueGenerator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\NumberRangeEvents;
use Shopware\Core\System\NumberRange\NumberRangeException;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternDate;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternIncrement;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(NumberRangeValueGenerator::class)]
class NumberRangeValueGeneratorTest extends TestCase
{
    public function testGeneratedNumberValue(): void
    {
        $dispatcher = new EventDispatcher();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => Uuid::randomHex(),
                'pattern' => 'ABC{n}',
                'start' => 0,
            ]);

        $result = $this->createMock(Result::class);
        $result->expects($this->once())
            ->method('fetchOne')
            ->willReturn('1');

        $connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $numberRangeValueGenerator = new NumberRangeValueGenerator(
            new ValueGeneratorPatternRegistry([
                new ValueGeneratorPatternIncrement(
                    new IncrementSqlStorage($connection, new NativeClock()),
                ),
            ]),
            $dispatcher,
            $connection,
        );

        $value = $numberRangeValueGenerator->getValue(OrderDefinition::ENTITY_NAME, Context::createDefaultContext(), null, false);
        static::assertSame('ABC1', $value);
    }

    public function testGeneratedEventIsDispatched(): void
    {
        $dispatcher = new EventDispatcher();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => Uuid::randomHex(),
                'pattern' => '{n}',
                'start' => 0,
            ]);

        $numberRangeValueGenerator = new NumberRangeValueGenerator(
            new ValueGeneratorPatternRegistry([]),
            $dispatcher,
            $connection,
        );

        $post = $this->createMock(CallableClass::class);
        $post->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener(NumberRangeEvents::NUMBER_RANGE_GENERATED, $post);

        $numberRangeValueGenerator->getValue(OrderDefinition::ENTITY_NAME, Context::createDefaultContext(), null, false);
    }

    public function testGenerateStandardPattern(): void
    {
        $value = $this->getGenerator('Pre_{n}_suf')->getValue(ProductDefinition::class, Context::createDefaultContext(), null);
        static::assertSame('Pre_5_suf', $value);
    }

    public function testGenerateDatePattern(): void
    {
        $value = $this->getGenerator('Pre_{date}_suf')->getValue(ProductDefinition::class, Context::createDefaultContext(), null);
        static::assertSame('Pre_' . date(ValueGeneratorPatternDate::STANDARD_FORMAT) . '_suf', $value);
    }

    public function testGenerateDateWithFormatPattern(): void
    {
        $value = $this->getGenerator('Pre_{date_ymd}_suf')->getValue(ProductDefinition::class, Context::createDefaultContext(), null);
        static::assertSame('Pre_' . date('ymd') . '_suf', $value);
    }

    public function testGenerateAllPatterns(): void
    {
        $value = $this->getGenerator('Pre_{date}_{date_ymd}_{n}_suf')->getValue(ProductDefinition::class, Context::createDefaultContext(), null);
        static::assertSame(
            'Pre_' . date(ValueGeneratorPatternDate::STANDARD_FORMAT) . '_' . date('ymd') . '_5_suf',
            $value
        );
    }

    public function testPreviewPatternByNumberRangeIdUsesPersistedNumberRange(): void
    {
        $numberRangeId = Uuid::randomHex();

        $incrPattern = $this->createMock(ValueGeneratorPatternIncrement::class);
        $incrPattern->method('getPatternId')->willReturn('n');
        $incrPattern->expects($this->once())
            ->method('generate')
            ->with(
                static::callback(static fn (array $config): bool => $config['id'] === $numberRangeId && $config['pattern'] === 'ABC{n}' && $config['start'] === 10),
                [],
                true
            )
            ->willReturn('10');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => $numberRangeId,
                'pattern' => 'ABC{n}',
                'start' => '10',
            ]);

        $generator = new NumberRangeValueGenerator(
            new ValueGeneratorPatternRegistry([$incrPattern]),
            new EventDispatcher(),
            $connection,
        );

        static::assertSame('ABC10', $generator->previewPatternByNumberRangeId($numberRangeId));
    }

    public function testPreviewPatternByNumberRangeIdUsesOverrides(): void
    {
        $numberRangeId = Uuid::randomHex();

        $incrPattern = $this->createMock(ValueGeneratorPatternIncrement::class);
        $incrPattern->method('getPatternId')->willReturn('n');
        $incrPattern->expects($this->once())
            ->method('generate')
            ->with(
                static::callback(static fn (array $config): bool => $config['id'] === $numberRangeId && $config['pattern'] === 'ORD-{n}' && $config['start'] === 42),
                [],
                true
            )
            ->willReturn('42');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => $numberRangeId,
                'pattern' => 'ABC{n}',
                'start' => '10',
            ]);

        $generator = new NumberRangeValueGenerator(
            new ValueGeneratorPatternRegistry([$incrPattern]),
            new EventDispatcher(),
            $connection,
        );

        static::assertSame('ORD-42', $generator->previewPatternByNumberRangeId($numberRangeId, 'ORD-{n}', 42));
    }

    public function testPreviewPatternByNumberRangeIdThrowsForMissingNumberRange(): void
    {
        $numberRangeId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $generator = new NumberRangeValueGenerator(
            new ValueGeneratorPatternRegistry([]),
            new EventDispatcher(),
            $connection,
        );

        $this->expectExceptionObject(NumberRangeException::numberRangeNotFound($numberRangeId));

        $generator->previewPatternByNumberRangeId($numberRangeId);
    }

    public function testDeprecatedPreviewPatternThrowsWhenMajorFeatureIsActive(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('fetchAssociative');

        $generator = new NumberRangeValueGenerator(
            new ValueGeneratorPatternRegistry([]),
            new EventDispatcher(),
            $connection,
        );

        Feature::fake(['v6.8.0.0'], function () use ($generator): void {
            $this->expectException(FeatureException::class);

            $generator->previewPattern('customer', '{n}', 0);
        });
    }

    public function testGenerateExtraCharsAllPatterns(): void
    {
        $value = $this->getGenerator('Pre_!"§$%&/()=_{date}_{date_ymd}_{n}_suf')->getValue(ProductDefinition::class, Context::createDefaultContext(), null);
        static::assertSame(
            'Pre_!"§$%&/()=_' . date(ValueGeneratorPatternDate::STANDARD_FORMAT) . '_' . date('ymd') . '_5_suf',
            $value
        );
    }

    private function getGenerator(string $pattern): NumberRangeValueGenerator
    {
        $incrPattern = $this->createMock(ValueGeneratorPatternIncrement::class);
        $incrPattern->method('getPatternId')->willReturn('n');
        $incrPattern->method('generate')->willReturn('5');

        $patternReg = new ValueGeneratorPatternRegistry([$incrPattern, new ValueGeneratorPatternDate()]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['id' => Uuid::randomHex(), 'pattern' => $pattern, 'start' => 1]);

        return new NumberRangeValueGenerator(
            $patternReg,
            new EventDispatcher(),
            $connection,
        );
    }
}
