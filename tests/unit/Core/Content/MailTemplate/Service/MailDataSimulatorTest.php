<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFieldEvent;
use Shopware\Core\Content\MailTemplate\Service\MailDataSimulator;
use Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsField;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\AbstractProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CronIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
        $definition = new TestMailTemplateEntityDefinition(new FieldCollection([
            (new EmailField('email', 'email'))->addFlags(new ApiAware()),
        ]));

        $simulator = $this->createSimulator(
            [
                'testEntity' => [
                    'type' => EntityType::TYPE,
                    'entityClass' => TestMailTemplateEntityDefinition::ENTITY_NAME,
                ],
            ],
            $definition
        );

        $result = $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertInstanceOf(ArrayEntity::class, $result['testEntity']);
        static::assertIsString($result['testEntity']->get('email'));
        static::assertNotFalse(filter_var($result['testEntity']->get('email'), \FILTER_VALIDATE_EMAIL));
    }

    public function testGenerateFieldDataUsesNumberRangeFieldSimulationForStringSubclass(): void
    {
        $definition = new TestMailTemplateEntityDefinition(new FieldCollection([
            (new NumberRangeField('number_range', 'numberRange'))->addFlags(new ApiAware()),
        ]));

        $simulator = $this->createSimulator(
            [
                'testEntity' => [
                    'type' => EntityType::TYPE,
                    'entityClass' => TestMailTemplateEntityDefinition::ENTITY_NAME,
                ],
            ],
            $definition
        );

        $result = $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertInstanceOf(ArrayEntity::class, $result['testEntity']);
        static::assertIsString($result['testEntity']->get('numberRange'));
        static::assertMatchesRegularExpression('/^"\d+"$/', $result['testEntity']->get('numberRange'));
    }

    public function testGenerateFieldDataUsesParentFkFallbackBeforeFkFieldLogic(): void
    {
        $definition = new TestMailTemplateEntityDefinition(new FieldCollection([
            (new ParentFkField('dummy_definition'))->addFlags(new ApiAware()),
        ]));

        $simulator = $this->createSimulator(
            [
                'testEntity' => [
                    'type' => EntityType::TYPE,
                    'entityClass' => TestMailTemplateEntityDefinition::ENTITY_NAME,
                ],
            ],
            $definition
        );

        $result = $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertInstanceOf(ArrayEntity::class, $result['testEntity']);
        static::assertNull($result['testEntity']->get('parentId'));
    }

    public function testGenerateFieldDataReturnsNullForUnknownFieldType(): void
    {
        $definition = new TestMailTemplateEntityDefinition(new FieldCollection([
            (new UnknownTestField('unknown'))->addFlags(new ApiAware()),
        ]));

        $simulator = $this->createSimulator(
            [
                'testEntity' => [
                    'type' => EntityType::TYPE,
                    'entityClass' => TestMailTemplateEntityDefinition::ENTITY_NAME,
                ],
            ],
            $definition
        );

        $result = $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertInstanceOf(ArrayEntity::class, $result['testEntity']);
        static::assertNull($result['testEntity']->get('unknown'));
    }

    public function testFieldEventCanOverrideSubclassOfKnownCoreFieldType(): void
    {
        $capturedEvent = null;
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) use (&$capturedEvent): object {
            if ($event instanceof MailDataSimulatorFieldEvent) {
                $capturedEvent = $event;

                if ($event->field instanceof CustomStringField) {
                    $event->setValue('event-value');
                }
            }

            return $event;
        });

        $definition = new TestMailTemplateEntityDefinition(new FieldCollection([
            (new CustomStringField('custom_string', 'customString'))->addFlags(new ApiAware()),
        ]));

        $simulator = $this->createSimulator(
            [
                'testEntity' => [
                    'type' => EntityType::TYPE,
                    'entityClass' => TestMailTemplateEntityDefinition::ENTITY_NAME,
                ],
            ],
            $definition,
            $dispatcher
        );

        $result = $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertInstanceOf(ArrayEntity::class, $result['testEntity']);
        static::assertSame('event-value', $result['testEntity']->get('customString'));
        static::assertInstanceOf(MailDataSimulatorFieldEvent::class, $capturedEvent);
        static::assertInstanceOf(CustomStringField::class, $capturedEvent->field);
    }

    public function testGenerateEventDataTypeDataStillSimulatesScalarFloat(): void
    {
        $simulator = $this->createSimulator([
            'score' => ['type' => ScalarValueType::TYPE_FLOAT],
        ]);

        $result = $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertIsFloat($result['score']);
        static::assertGreaterThanOrEqual(1, $result['score']);
        static::assertLessThanOrEqual(10000, $result['score']);
    }

    public function testGetTemplateDataUsesProviderCriteriaForEntityEventData(): void
    {
        $definition = new TestMailTemplateEntityDefinition(new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
        ]));

        $provider = new TestMailTemplateProvider(
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ContainerInterface::class),
            [],
        );
        $provider->wasCalled = false;

        $simulator = $this->createSimulator(
            [
                'testEntity' => [
                    'type' => EntityType::TYPE,
                    'entityClass' => TestMailTemplateEntityDefinition::ENTITY_NAME,
                ],
            ],
            $definition,
            null,
            [TestMailTemplateEntityDefinition::ENTITY_NAME => $provider]
        );

        $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertTrue($provider->wasCalled);
    }

    public function testGetTemplateDataKeepsAttributeEntityAssociationsSeparated(): void
    {
        $definition = new TestMailTemplateEntityDefinition(new FieldCollection([
            (new ManyToOneAssociationField('firstAttribute', 'first_attribute_id', 'first_attribute_entity', 'id', true))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('secondAttribute', 'second_attribute_id', 'second_attribute_entity', 'id', true))->addFlags(new ApiAware()),
        ]));

        $firstAttributeDefinition = new AttributeEntityDefinition([
            'entity_name' => 'first_attribute_entity',
            'entity_class' => Entity::class,
            'collection_class' => EntityCollection::class,
            'hydrator_class' => EntityHydrator::class,
            'fields' => [
                [
                    'class' => IdField::class,
                    'args' => ['id', 'id'],
                    'translated' => false,
                    'flags' => [['class' => ApiAware::class, 'args' => []]],
                ],
                [
                    'class' => StringField::class,
                    'args' => ['name', 'name'],
                    'translated' => false,
                    'flags' => [['class' => ApiAware::class, 'args' => []]],
                ],
            ],
        ]);

        $secondAttributeDefinition = new AttributeEntityDefinition([
            'entity_name' => 'second_attribute_entity',
            'entity_class' => Entity::class,
            'collection_class' => EntityCollection::class,
            'hydrator_class' => EntityHydrator::class,
            'fields' => [
                [
                    'class' => IdField::class,
                    'args' => ['id', 'id'],
                    'translated' => false,
                    'flags' => [['class' => ApiAware::class, 'args' => []]],
                ],
                [
                    'class' => StringField::class,
                    'args' => ['name', 'name'],
                    'translated' => false,
                    'flags' => [['class' => ApiAware::class, 'args' => []]],
                ],
            ],
        ]);

        $simulator = $this->createSimulator(
            [
                'testEntity' => [
                    'type' => EntityType::TYPE,
                    'entityClass' => TestMailTemplateEntityDefinition::ENTITY_NAME,
                ],
            ],
            $definition,
            null,
            [],
            [$firstAttributeDefinition, $secondAttributeDefinition]
        );

        $result = $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertInstanceOf(ArrayEntity::class, $result['testEntity']);
        static::assertInstanceOf(Entity::class, $result['testEntity']->get('firstAttribute'));
        static::assertInstanceOf(Entity::class, $result['testEntity']->get('secondAttribute'));
        static::assertNotSame(
            $result['testEntity']->get('firstAttribute')->get('name'),
            $result['testEntity']->get('secondAttribute')->get('name')
        );
    }

    public function testMappingDefinitionDoesNotThrowException(): void
    {
        $definition = new TestMailTemplateEntityDefinition(new FieldCollection([
            (new OneToManyAssociationField('mappingChildren', TestMappingDefinition::class, 'test_mail_template_entity_id'))->addFlags(new ApiAware()),
        ]));

        $provider = new TestMailTemplateProvider(
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ContainerInterface::class),
            ['mappingChildren'],
        );

        $simulator = $this->createSimulator(
            [
                'testEntity' => [
                    'type' => EntityType::TYPE,
                    'entityClass' => TestMailTemplateEntityDefinition::ENTITY_NAME,
                ],
            ],
            $definition,
            null,
            [TestMailTemplateEntityDefinition::ENTITY_NAME => $provider],
            [new TestMappingDefinition()]
        );

        $result = $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertInstanceOf(ArrayEntity::class, $result['testEntity']);

        $mappingChildren = $result['testEntity']->get('mappingChildren');
        static::assertInstanceOf(EntityCollection::class, $mappingChildren);
        static::assertCount(1, $mappingChildren);
        static::assertInstanceOf(Entity::class, $mappingChildren->first());
    }

    public function testRuntimeFieldsAreIgnored(): void
    {
        $definition = new TestMailTemplateEntityDefinition(new FieldCollection([
            (new StringField('visible_field', 'visibleField'))->addFlags(new ApiAware()),
            (new StringField('runtime_field', 'runtimeField'))->addFlags(new ApiAware(), new Runtime()),
        ]));

        $simulator = $this->createSimulator(
            [
                'testEntity' => [
                    'type' => EntityType::TYPE,
                    'entityClass' => TestMailTemplateEntityDefinition::ENTITY_NAME,
                ],
            ],
            $definition
        );

        $result = $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertInstanceOf(ArrayEntity::class, $result['testEntity']);
        static::assertTrue($result['testEntity']->has('visibleField'));
        static::assertIsString($result['testEntity']->get('visibleField'));
        static::assertFalse($result['testEntity']->has('runtimeField'));
    }

    /**
     * @param \Closure(mixed): void $assertValue
     */
    #[DataProvider('generateFieldDataSimpleFieldProvider')]
    public function testGenerateFieldDataReturnsExpectedValueForSimpleFieldCases(Field $field, \Closure $assertValue): void
    {
        $assertValue($this->getGeneratedFieldValue($field));
    }

    /**
     * @return \Generator<string, array{Field, \Closure(mixed): void}>
     */
    public static function generateFieldDataSimpleFieldProvider(): \Generator
    {
        yield 'auto increment field' => [
            new AutoIncrementField(),
            static function (mixed $value): void {
                static::assertIsInt($value);
                static::assertGreaterThanOrEqual(0, $value);
                static::assertLessThanOrEqual(1000, $value);
            },
        ];

        yield 'blob field' => [
            new BlobField('payload', 'payload'),
            static function (mixed $value): void {
                static::assertSame('payload', $value);
            },
        ];

        yield 'bool field' => [
            new BoolField('active', 'active'),
            static function (mixed $value): void {
                static::assertFalse($value);
            },
        ];

        yield 'locked field' => [
            new LockedField(),
            static function (mixed $value): void {
                static::assertFalse($value);
            },
        ];

        yield 'parent association field' => [
            new ParentAssociationField(TestReferenceDefinition::class),
            static function (mixed $value): void {
                static::assertNull($value);
            },
        ];

        yield 'parent foreign key field' => [
            new ParentFkField(TestReferenceDefinition::class),
            static function (mixed $value): void {
                static::assertNull($value);
            },
        ];

        yield 'calculated price field' => [
            new CalculatedPriceField('calculated_price', 'calculatedPrice'),
            static function (mixed $value): void {
                static::assertInstanceOf(CalculatedPrice::class, $value);
            },
        ];

        yield 'cart price field' => [
            new CartPriceField('cart_price', 'cartPrice'),
            static function (mixed $value): void {
                static::assertInstanceOf(CartPrice::class, $value);
            },
        ];

        yield 'cash rounding config field' => [
            new CashRoundingConfigField('rounding', 'rounding'),
            static function (mixed $value): void {
                static::assertInstanceOf(CashRoundingConfig::class, $value);
                static::assertSame(2, $value->getDecimals());
                static::assertSame(0.01, $value->getInterval());
                static::assertFalse($value->roundForNet());
            },
        ];

        yield 'child count field' => [
            new ChildCountField(),
            static function (mixed $value): void {
                static::assertIsInt($value);
                static::assertGreaterThanOrEqual(1, $value);
                static::assertLessThanOrEqual(999999, $value);
            },
        ];

        yield 'tree level field' => [
            new TreeLevelField('level', 'level'),
            static function (mixed $value): void {
                static::assertIsInt($value);
                static::assertGreaterThanOrEqual(1, $value);
                static::assertLessThanOrEqual(999999, $value);
            },
        ];

        yield 'int field' => [
            new IntField('quantity', 'quantity'),
            static function (mixed $value): void {
                static::assertIsInt($value);
                static::assertGreaterThanOrEqual(1, $value);
                static::assertLessThanOrEqual(999999, $value);
            },
        ];

        yield 'measurement units field' => [
            new MeasurementUnitsField('measurement_units', 'measurementUnits'),
            static function (mixed $value): void {
                static::assertInstanceOf(MeasurementUnits::class, $value);
                static::assertSame(MeasurementUnits::DEFAULT_MEASUREMENT_SYSTEM, $value->getSystem());
            },
        ];

        yield 'price definition field' => [
            new PriceDefinitionField('price_definition', 'priceDefinition'),
            static function (mixed $value): void {
                static::assertInstanceOf(AbsolutePriceDefinition::class, $value);
                static::assertSame(12.34, $value->getPrice());
            },
        ];

        yield 'enum field' => [
            new EnumField('status', 'status', TestMailTemplateEnum::Draft),
            static function (mixed $value): void {
                static::assertSame([], $value);
            },
        ];

        yield 'list field' => [
            new ListField('items', 'items'),
            static function (mixed $value): void {
                static::assertSame([], $value);
            },
        ];

        yield 'breadcrumb field' => [
            new BreadcrumbField('breadcrumb', 'breadcrumb'),
            static function (mixed $value): void {
                static::assertSame([], $value);
            },
        ];

        yield 'created at field' => [
            new CreatedAtField(),
            static function (mixed $value): void {
                static::assertInstanceOf(\DateTimeInterface::class, $value);
            },
        ];

        yield 'updated at field' => [
            new UpdatedAtField(),
            static function (mixed $value): void {
                static::assertInstanceOf(\DateTimeInterface::class, $value);
            },
        ];

        yield 'date field' => [
            new DateField('birthday', 'birthday'),
            static function (mixed $value): void {
                static::assertInstanceOf(\DateTimeInterface::class, $value);
            },
        ];

        yield 'date time field' => [
            new DateTimeField('expires_at', 'expiresAt'),
            static function (mixed $value): void {
                static::assertInstanceOf(\DateTimeInterface::class, $value);
            },
        ];

        yield 'cron interval field' => [
            new CronIntervalField('interval', 'interval'),
            static function (mixed $value): void {
                static::assertSame('8 * * * *', $value);
            },
        ];

        yield 'date interval field' => [
            new DateIntervalField('duration', 'duration'),
            static function (mixed $value): void {
                static::assertSame('P0Y0M0DT0H30M0S', $value);
            },
        ];

        yield 'email field' => [
            new EmailField('email', 'email'),
            static function (mixed $value): void {
                static::assertSame('max.mustermann@example.com', $value);
            },
        ];

        yield 'float field' => [
            new FloatField('amount', 'amount'),
            static function (mixed $value): void {
                static::assertIsFloat($value);
                static::assertGreaterThanOrEqual(1, $value);
                static::assertLessThanOrEqual(10000, $value);
            },
        ];

        yield 'id field' => [
            new IdField('id', 'id'),
            static function (mixed $value): void {
                static::assertIsString($value);
                static::assertTrue(Uuid::isValid($value));
            },
        ];

        yield 'tree path field' => [
            new TreePathField('path', 'path'),
            static function (mixed $value): void {
                static::assertSame('Lorem ipsum dolor sit amet.', $value);
            },
        ];

        yield 'long text field' => [
            new LongTextField('description', 'description'),
            static function (mixed $value): void {
                static::assertSame('Lorem ipsum dolor sit amet.', $value);
            },
        ];

        yield 'number range field' => [
            new NumberRangeField('number', 'number'),
            static function (mixed $value): void {
                static::assertIsString($value);
                static::assertMatchesRegularExpression('/^"\d+"$/', $value);
            },
        ];

        yield 'password field' => [
            new PasswordField('password', 'password'),
            static function (mixed $value): void {
                static::assertSame('P@ssw0rd!', $value);
            },
        ];

        yield 'remote address field' => [
            new RemoteAddressField('ip_address', 'ipAddress'),
            static function (mixed $value): void {
                static::assertSame('"192.0.2.0"', $value);
            },
        ];

        yield 'time zone field' => [
            new TimeZoneField('time_zone', 'timeZone'),
            static function (mixed $value): void {
                static::assertSame('UTC', $value);
            },
        ];

        yield 'string field' => [
            new StringField('name', 'name'),
            static function (mixed $value): void {
                static::assertIsString($value);
                static::assertNotSame('', $value);
            },
        ];

        yield 'json field' => [
            new JsonField('payload', 'payload', [new StringField('nested', 'nested')]),
            static function (mixed $value): void {
                self::assertIsArray($value);
                self::assertArrayHasKey('nested', $value);
                static::assertIsString($value['nested']);
                static::assertNotSame('', $value['nested']);
            },
        ];
    }

    public function testGenerateFieldDataReturnsTranslationCollectionWithLanguageIdentifier(): void
    {
        $languageDefinition = new TestLanguageDefinition();
        $value = $this->getGeneratedFieldValue(
            new TranslationsAssociationField(TestTranslationDefinition::class, 'test_reference_id'),
            [new TestTranslationDefinition(), $languageDefinition],
            [LanguageDefinition::class => $languageDefinition],
            ['translations']
        );

        static::assertInstanceOf(EntityCollection::class, $value);
        static::assertCount(1, $value);

        $translation = $value->first();
        static::assertInstanceOf(ArrayEntity::class, $translation);
        static::assertTrue(Uuid::isValid($translation->getUniqueIdentifier()));
    }

    /**
     * @param array<string, mixed> $eventData
     * @param iterable<string, AbstractProvider<Entity, EntityCollection<Entity>>> $dataProviders
     * @param list<EntityDefinition> $additionalDefinitions
     * @param array<string, EntityDefinition> $definitionAliases
     */
    private function createSimulator(
        array $eventData,
        ?TestMailTemplateEntityDefinition $eventEntityDefinition = null,
        ?EventDispatcherInterface $dispatcher = null,
        iterable $dataProviders = [],
        array $additionalDefinitions = [],
        array $definitionAliases = [],
    ): MailDataSimulator {
        $response = new BusinessEventCollectorResponse();
        $response->set('test.flow', new BusinessEventDefinition('test.flow', TestMailAwareEvent::class, $eventData));

        $businessEventCollector = static::createStub(BusinessEventCollector::class);
        $businessEventCollector->method('collect')->willReturn($response);

        $salesChannelDefinition = new TestSalesChannelDefinition();
        $definitions = [
            $salesChannelDefinition,
        ];

        if ($eventEntityDefinition !== null) {
            $definitions[] = $eventEntityDefinition;
        }

        $definitions = [...$definitions, ...$additionalDefinitions];

        $definitionAliases[SalesChannelDefinition::class] = $salesChannelDefinition;
        $definitionRegistry = $this->createDefinitionRegistry($definitions, $definitionAliases);

        $providerDispatcher = static::createStub(EventDispatcherInterface::class);
        $providerContainer = static::createStub(ContainerInterface::class);
        /** @var array<string, AbstractProvider<Entity, EntityCollection<Entity>>> $providerMap */
        $providerMap = [
            SalesChannelDefinition::ENTITY_NAME => new SalesChannelProvider($providerDispatcher, $providerContainer),
            ...$dataProviders,
        ];

        return new MailDataSimulator(
            $businessEventCollector,
            $definitionRegistry,
            $dispatcher ?? static::createStub(EventDispatcherInterface::class),
            $providerMap,
            new NativeClock(),
        );
    }

    /**
     * @param list<EntityDefinition> $additionalDefinitions
     * @param array<string, EntityDefinition> $definitionAliases
     * @param list<string> $associations
     */
    private function getGeneratedFieldValue(
        Field $field,
        array $additionalDefinitions = [],
        array $definitionAliases = [],
        array $associations = []
    ): mixed {
        $definition = new TestMailTemplateEntityDefinition(new FieldCollection([$field]));
        $dataProviders = [];

        if ($associations !== []) {
            $dataProviders[TestMailTemplateEntityDefinition::ENTITY_NAME] = new TestMailTemplateProvider(
                static::createStub(EventDispatcherInterface::class),
                static::createStub(ContainerInterface::class),
                $associations
            );
        }

        $simulator = $this->createSimulator(
            [
                'testEntity' => [
                    'type' => EntityType::TYPE,
                    'entityClass' => TestMailTemplateEntityDefinition::ENTITY_NAME,
                ],
            ],
            $definition,
            null,
            $dataProviders,
            $additionalDefinitions,
            $definitionAliases
        );

        $result = $simulator->getTemplateData('test.flow', Context::createDefaultContext());

        static::assertInstanceOf(ArrayEntity::class, $result['testEntity']);

        return $result['testEntity']->get($field->getPropertyName());
    }

    /**
     * @param list<EntityDefinition> $definitions
     * @param array<string, EntityDefinition> $definitionAliases
     */
    private function createDefinitionRegistry(array $definitions, array $definitionAliases = []): DefinitionInstanceRegistry
    {
        foreach ($definitionAliases as $definition) {
            if (!\in_array($definition, $definitions, true)) {
                $definitions[] = $definition;
            }
        }

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);

        foreach ($definitions as $definition) {
            $definition->compile($definitionRegistry);
        }

        $definitionMap = [];
        foreach ($definitions as $definition) {
            $definitionMap[$definition->getEntityName()] = $definition;

            if (!isset($definitionMap[$definition->getClass()])) {
                $definitionMap[$definition->getClass()] = $definition;
            }
        }

        foreach ($definitionAliases as $definitionClassOrEntityName => $definition) {
            $definitionMap[$definitionClassOrEntityName] = $definition;
        }

        $definitionRegistry->method('getByClassOrEntityName')
            ->willReturnCallback(function (string $definitionClassOrEntityName) use ($definitionMap) {
                if (!isset($definitionMap[$definitionClassOrEntityName])) {
                    throw new \RuntimeException(\sprintf('Unknown definition %s', $definitionClassOrEntityName));
                }

                return $definitionMap[$definitionClassOrEntityName];
            });

        $definitionRegistry->method('getSerializer')
            ->willReturnCallback(static function (): FieldSerializerInterface {
                return new TestFieldSerializer();
            });
        $definitionRegistry->method('getAccessorBuilder')->willReturn(static::createStub(FieldAccessorBuilderInterface::class));

        return $definitionRegistry;
    }
}

/**
 * @internal
 */
class CustomStringField extends StringField
{
}

/**
 * @internal
 */
class UnknownTestField extends Field
{
    protected function getSerializerClass(): string
    {
        return StringFieldSerializer::class;
    }
}

/**
 * @internal
 */
class TestMailAwareEvent implements MailAware
{
    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([]);
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }
}

/**
 * @internal
 */
class TestMailTemplateEntityDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'test_mail_template_entity';

    public function __construct(private readonly FieldCollection $definitionFields)
    {
        parent::__construct();
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return $this->definitionFields;
    }
}

/**
 * @internal
 */
class TestReferenceDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_reference';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
        ]);
    }
}

/**
 * @internal
 */
class TestLanguageDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'language';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
        ]);
    }
}

/**
 * @internal
 */
class TestTranslationDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_translation';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
        ]);
    }
}

/**
 * @internal
 */
class TestSalesChannelDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'sales_channel';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection();
    }
}

/**
 * @internal
 *
 * @extends AbstractProvider<Entity, EntityCollection<Entity>>
 */
class TestMailTemplateProvider extends AbstractProvider
{
    public bool $wasCalled = false;

    /**
     * @param list<string> $associations
     */
    public function __construct(
        EventDispatcherInterface $dispatcher,
        ContainerInterface $container,
        private readonly array $associations
    ) {
        parent::__construct($dispatcher, $container);
    }

    public function getEntityName(): string
    {
        return TestMailTemplateEntityDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $this->wasCalled = true;

        $criteria = new Criteria([$entityId]);

        foreach ($this->associations as $association) {
            $criteria->addAssociation($association);
        }

        return $criteria;
    }
}

/**
 * @internal
 */
class TestMappingDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_mapping';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
        ]);
    }
}

/**
 * @internal
 */
class TestFieldSerializer implements FieldSerializerInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        return $data;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        yield from [];
    }

    public function decode(Field $field, mixed $value): mixed
    {
        return json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);
    }
}

/**
 * @internal
 */
enum TestMailTemplateEnum: string
{
    case Draft = 'draft';
}
