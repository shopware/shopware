<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFieldEvent;
use Shopware\Core\Content\MailTemplate\Service\MailDataSimulator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(MailDataSimulator::class)]
#[Package('after-sales')]
class MailDataSimulatorTest extends TestCase
{
    public function testGenerateFieldDataUsesEmailFieldSimulationForStringSubclass(): void
    {
        $result = $this->invokeGenerateFieldData(
            new EmailField('email', 'email')
        );

        static::assertIsString($result);
        static::assertNotFalse(filter_var($result, \FILTER_VALIDATE_EMAIL));
    }

    public function testGenerateFieldDataUsesNumberRangeFieldSimulationForStringSubclass(): void
    {
        $result = $this->invokeGenerateFieldData(
            new NumberRangeField('number_range', 'numberRange')
        );

        static::assertIsString($result);
        static::assertMatchesRegularExpression('/^"\d+"$/', $result);
    }

    public function testGenerateFieldDataUsesParentFkFallbackBeforeFkFieldLogic(): void
    {
        $result = $this->invokeGenerateFieldData(
            new ParentFkField('dummy_definition')
        );

        static::assertNull($result);
    }

    public function testGenerateFieldDataReturnsNullForUnknownFieldType(): void
    {
        $result = $this->invokeGenerateFieldData(
            new UnknownTestField('unknown')
        );

        static::assertNull($result);
    }

    public function testFieldEventCanOverrideSubclassOfKnownCoreFieldType(): void
    {
        $context = Context::createDefaultContext();
        $eventHolder = new \stdClass();
        $eventHolder->event = null;
        $dispatcher = new class($eventHolder, $context) implements EventDispatcherInterface {
            public function __construct(
                private readonly \stdClass $eventHolder,
                private readonly Context $context,
            ) {
            }

            public function dispatch(object $event, ?string $eventName = null): object
            {
                if ($event instanceof MailDataSimulatorFieldEvent) {
                    $this->eventHolder->event = $event;

                    if ($event->getField() instanceof CustomStringField) {
                        TestCase::assertSame($this->context, $event->getContext());
                        $event->setValue('event-value');
                    }
                }

                return $event;
            }
        };

        $result = $this->invokeGenerateFieldData(
            new CustomStringField('custom_string', 'customString'),
            $dispatcher,
            $context
        );

        static::assertSame('event-value', $result);
        static::assertInstanceOf(MailDataSimulatorFieldEvent::class, $eventHolder->event);
        static::assertInstanceOf(CustomStringField::class, $eventHolder->event->getField());
        static::assertInstanceOf(Generator::class, $eventHolder->event->getFaker());
    }

    public function testGenerateEventDataTypeDataStillSimulatesScalarFloat(): void
    {
        $simulator = $this->createSimulator();
        $faker = Factory::create();
        $faker->seed(1234);
        $entityCache = [];
        $context = Context::createDefaultContext();

        $result = (function (array $dataType, Context $context, Generator $faker) use (&$entityCache): mixed {
            return $this->generateEventDataTypeData($dataType, $entityCache, $context, $faker);
        })->call($simulator, ['type' => ScalarValueType::TYPE_FLOAT], $context, $faker);

        static::assertIsFloat($result);
        static::assertGreaterThanOrEqual(1, $result);
        static::assertLessThanOrEqual(10000, $result);
    }

    private function invokeGenerateFieldData(
        Field $field,
        ?EventDispatcherInterface $dispatcher = null,
        ?Context $context = null
    ): mixed {
        $simulator = $this->createSimulator($dispatcher);
        $faker = Factory::create();
        $faker->seed(1234);
        $entityCache = [];

        return (function (Field $field, Generator $faker, Context $context) use (&$entityCache): mixed {
            return $this->generateFieldData($field, $entityCache, $faker, $context);
        })->call($simulator, $field, $faker, $context ?? Context::createDefaultContext());
    }

    private function createSimulator(?EventDispatcherInterface $dispatcher = null): MailDataSimulator
    {
        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new TestEntityRepository();

        return new MailDataSimulator(
            new TestBusinessEventCollector(),
            new TestDefinitionInstanceRegistry(),
            $languageRepository,
            $dispatcher ?? new class implements EventDispatcherInterface {
                public function dispatch(object $event, ?string $eventName = null): object
                {
                    return $event;
                }
            },
            []
        );
    }
}

#[Package('after-sales')]
class CustomStringField extends \Shopware\Core\Framework\DataAbstractionLayer\Field\StringField
{
}

#[Package('after-sales')]
class UnknownTestField extends Field
{
    protected function getSerializerClass(): string
    {
        return StringFieldSerializer::class;
    }
}

#[Package('after-sales')]
class TestBusinessEventCollector extends BusinessEventCollector
{
    public function __construct()
    {
    }

    public function collect(Context $context): BusinessEventCollectorResponse
    {
        return new BusinessEventCollectorResponse();
    }
}

#[Package('after-sales')]
class TestDefinitionInstanceRegistry extends DefinitionInstanceRegistry
{
    public function __construct()
    {
    }

    public function get(string $class): EntityDefinition
    {
        throw new \RuntimeException('Not needed in this test.');
    }

    public function getByEntityName(string $entityName): EntityDefinition
    {
        throw new \RuntimeException('Not needed in this test.');
    }

    public function getByClassOrEntityName(string $key): EntityDefinition
    {
        throw new \RuntimeException('Not needed in this test.');
    }
}

#[Package('after-sales')]
class TestEntityRepository extends EntityRepository
{
    public function __construct()
    {
    }
}
