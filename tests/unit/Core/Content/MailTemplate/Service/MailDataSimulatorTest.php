<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFieldEvent;
use Shopware\Core\Content\MailTemplate\Service\MailDataSimulator;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\AbstractProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\SalesChannelProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
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

        $provider = new class($this->createStub(EventDispatcherInterface::class), $this->createStub(ContainerInterface::class)) extends AbstractProvider {
            public bool $wasCalled = false;

            public function getEntityName(): string
            {
                return TestMailTemplateEntityDefinition::ENTITY_NAME;
            }

            protected function constructCriteria(string $entityId): Criteria
            {
                $this->wasCalled = true;

                return new Criteria([$entityId]);
            }
        };

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

    /**
     * @param array<string, mixed> $eventData
     * @param iterable<string, AbstractProvider<Entity, EntityCollection<Entity>>> $dataProviders
     * @param list<EntityDefinition> $additionalDefinitions
     */
    private function createSimulator(
        array $eventData,
        ?TestMailTemplateEntityDefinition $eventEntityDefinition = null,
        ?EventDispatcherInterface $dispatcher = null,
        iterable $dataProviders = [],
        array $additionalDefinitions = [],
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

        $definitionMap[SalesChannelDefinition::class] = $salesChannelDefinition;

        $definitionRegistry->method('getByClassOrEntityName')
            ->willReturnCallback(function (string $definitionClassOrEntityName) use ($definitionMap) {
                if (!isset($definitionMap[$definitionClassOrEntityName])) {
                    throw new \RuntimeException(\sprintf('Unknown definition %s', $definitionClassOrEntityName));
                }

                return $definitionMap[$definitionClassOrEntityName];
            });

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
