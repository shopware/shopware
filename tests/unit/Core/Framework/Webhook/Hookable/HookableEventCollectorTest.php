<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity as EntityAttribute;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;

/**
 * @internal
 */
#[CoversClass(HookableEventCollector::class)]
class HookableEventCollectorTest extends TestCase
{
    private HookableEventCollector $hookableEventCollector;

    private BusinessEventCollector $businessEventCollector;

    private DefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->businessEventCollector = static::createStub(BusinessEventCollector::class);
        $this->definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $hookableEntityDefinitions = [
            $this->createProductDefinition(),
            new TestEntityWithAttribute(),
        ];

        $this->hookableEventCollector = new HookableEventCollector(
            $this->businessEventCollector,
            $this->definitionRegistry,
            new \ArrayIterator($hookableEntityDefinitions)
        );
    }

    public function testGetHookableEventNamesWithPrivilegesReturnsCorrectStructure(): void
    {
        $context = Context::createDefaultContext();
        $result = $this->hookableEventCollector->getHookableEventNamesWithPrivileges($context);

        static::assertIsArray($result);
        static::assertNotEmpty($result);

        // Check that we have entity written events
        static::assertArrayHasKey('product.written', $result);
        static::assertArrayHasKey('product.deleted', $result);
        static::assertArrayHasKey('test_entity.written', $result);
        static::assertArrayHasKey('test_entity.deleted', $result);

        // Check structure of entity written events
        static::assertArrayHasKey('privileges', $result['product.written']);
        static::assertSame(['product:read'], $result['product.written']['privileges']);
        static::assertSame(['product:read'], $result['product.deleted']['privileges']);

        // Check that hookable events are included
        foreach (Hookable::HOOKABLE_EVENTS as $eventName) {
            static::assertArrayHasKey($eventName, $result);
            static::assertArrayHasKey('privileges', $result[$eventName]);
            static::assertSame([], $result[$eventName]['privileges']);
        }
    }

    public function testGetPrivilegesFromBusinessEventDefinition(): void
    {
        $productDefinition = $this->createProductDefinition();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('get')->willReturn($productDefinition);

        $hookableEventCollector = new HookableEventCollector(
            $this->businessEventCollector,
            $definitionRegistry,
            []
        );

        $businessEventDefinition = new BusinessEventDefinition(
            'test.event',
            'TestEvent',
            [
                'product' => [
                    'type' => 'entity',
                    'entityClass' => ProductDefinition::class,
                ],
                'string' => [
                    'type' => 'string',
                    'name' => 'someString',
                ],
            ]
        );

        $result = $hookableEventCollector->getPrivilegesFromBusinessEventDefinition($businessEventDefinition);

        static::assertCount(1, $result);
        static::assertSame('product:read', $result[0]);
    }

    public function testGetPrivilegesFromBusinessEventDefinitionWithNoEntityData(): void
    {
        $hookableEventCollector = new HookableEventCollector(
            $this->businessEventCollector,
            $this->definitionRegistry,
            []
        );

        $businessEventDefinition = new BusinessEventDefinition(
            'test.event',
            'TestEvent',
            [
                'string' => [
                    'type' => 'string',
                    'name' => 'someString',
                ],
                'array' => [
                    'type' => 'array',
                    'name' => 'someArray',
                ],
            ]
        );

        $result = $hookableEventCollector->getPrivilegesFromBusinessEventDefinition($businessEventDefinition);

        static::assertSame([], $result);
    }

    public function testGetEntityWrittenEventNamesWithPrivileges(): void
    {
        $result = $this->hookableEventCollector->getEntityWrittenEventNamesWithPrivileges();

        static::assertIsArray($result);

        // Check product events
        static::assertArrayHasKey('product.written', $result);
        static::assertArrayHasKey('product.deleted', $result);
        static::assertSame(['privileges' => ['product:read']], $result['product.written']);
        static::assertSame(['privileges' => ['product:read']], $result['product.deleted']);

        // Check test entity events
        static::assertArrayHasKey('test_entity.written', $result);
        static::assertArrayHasKey('test_entity.deleted', $result);
        static::assertSame(['privileges' => ['test_entity:read']], $result['test_entity.written']);
        static::assertSame(['privileges' => ['test_entity:read']], $result['test_entity.deleted']);
    }

    public function testGetHookableEntities(): void
    {
        $result = $this->hookableEventCollector->getHookableEntities();

        static::assertIsArray($result);
        static::assertContains('product', $result);
        static::assertContains('test_entity', $result);
        static::assertCount(2, $result);
    }

    public function testGetHookableEventNamesWithPrivilegesIncludesBusinessEvents(): void
    {
        $businessEventResponse = $this->createBusinessEventCollectorResponse();
        $businessEventCollector = static::createStub(BusinessEventCollector::class);
        $businessEventCollector->method('collect')->willReturn($businessEventResponse);

        $productDefinition = $this->createProductDefinition();
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('get')->willReturn($productDefinition);

        $hookableEventCollector = new HookableEventCollector(
            $businessEventCollector,
            $definitionRegistry,
            new \ArrayIterator([
                $this->createProductDefinition(),
                new TestEntityWithAttribute(),
            ])
        );

        $context = Context::createDefaultContext();
        $result = $hookableEventCollector->getHookableEventNamesWithPrivileges($context);

        static::assertArrayHasKey('checkout.customer.login', $result);
        static::assertArrayHasKey('privileges', $result['checkout.customer.login']);
        static::assertSame(['product:read'], $result['checkout.customer.login']['privileges']);
    }

    private function createProductDefinition(): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');

        return $definition;
    }

    private function createBusinessEventCollectorResponse(): BusinessEventCollectorResponse
    {
        $response = new BusinessEventCollectorResponse();

        $businessEvent = new BusinessEventDefinition(
            'checkout.customer.login',
            'CustomerLoginEvent',
            [
                'entity' => [
                    'type' => 'entity',
                    'entityClass' => ProductDefinition::class,
                ],
            ]
        );

        $response->set('checkout.customer.login', $businessEvent);

        return $response;
    }
}

// Create a test entity with the EntityAttribute for testing
/**
 * @internal Test fixture for entity with EntityAttribute
 */
#[EntityAttribute(name: 'test_entity')]
class TestEntityWithAttribute extends Entity
{
}
