<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\ValueGenerator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\NumberRangeEvents;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternIncrement;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;
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
                    new IncrementSqlStorage($connection),
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
}
