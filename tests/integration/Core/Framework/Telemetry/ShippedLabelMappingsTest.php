<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Telemetry;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cleanup\CleanupCartTask;
use Shopware\Core\Content\Flow\Telemetry\TriggerGroupResolver;
use Shopware\Core\Content\Mail\Telemetry\MailGroupResolver;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\TaskNameResolver;
use Shopware\Core\Framework\MessageQueue\Telemetry\MessageGroupResolver;
use Shopware\Core\Framework\Routing\Telemetry\DomainResolver;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\NumberRange\Telemetry\NumberRangeTypeResolver;
use Shopware\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pins the mapping tables core ships in `telemetry.yaml` as the container builds them: the config tree
 * (key normalization, validation, merge) and the parameter wiring into the registry, consumed by the real
 * resolvers. A drift does not fail at runtime — an unknown mapping name or a rewritten key silently falls
 * through to `other` — so this is the only place it becomes visible.
 *
 * The resolvers are constructed on the container's registry rather than fetched: single-consumer
 * resolvers are inlined at compile time and not reachable through the test container.
 *
 * Representative samples per table, not a mirror of the yaml: one exact entry, one prefix entry, one
 * precedence case, one fall-through. Resolver mechanics are covered by the resolver unit tests.
 *
 * @internal
 */
#[Package('framework')]
class ShippedLabelMappingsTest extends TestCase
{
    use KernelTestBehaviour;

    private const RESOLVER_MAPPINGS = [
        EntityGroupResolver::MAPPING,
        DomainResolver::MAPPING_ROUTE_DOMAIN,
        DomainResolver::MAPPING_ACTION_DOMAIN,
        MessageGroupResolver::MAPPING,
        TaskNameResolver::MAPPING,
        TriggerGroupResolver::MAPPING,
        MailGroupResolver::MAPPING,
        NumberRangeTypeResolver::MAPPING,
        SalesChannelTypeResolver::MAPPING,
    ];

    public function testResolverMappingsAndShippedTablesMatch(): void
    {
        $mappings = static::getContainer()->getParameter('shopware.telemetry.metrics.mappings');
        static::assertIsArray($mappings);

        // every resolver binding has a shipped table and every shipped table a consuming resolver
        static::assertEqualsCanonicalizing(self::RESOLVER_MAPPINGS, array_keys($mappings));

        // one resolver per mapping: all keys of a table come from a single identifier space (see the ADR)
        static::assertSame(self::RESOLVER_MAPPINGS, array_values(array_unique(self::RESOLVER_MAPPINGS)));
    }

    public function testShippedKeysKeepDashes(): void
    {
        $mappings = static::getContainer()->getParameter('shopware.telemetry.metrics.mappings');
        static::assertIsArray($mappings);

        // the config tree normalizes `-` to `_` unless the node opts out; route names and action segments
        // carry dashes and would never match otherwise
        static::assertArrayHasKey('store-api.product*', $mappings[DomainResolver::MAPPING_ROUTE_DOMAIN]);
        static::assertArrayHasKey('import-export', $mappings[DomainResolver::MAPPING_ACTION_DOMAIN]);
    }

    #[DataProvider('entityProvider')]
    public function testEntityGroup(string $entityName, string $expected): void
    {
        static::assertSame($expected, (new EntityGroupResolver($this->registry()))->resolve($entityName));
    }

    public static function entityProvider(): \Generator
    {
        yield 'exact entry' => ['main_category', 'category'];
        yield 'prefix entry' => ['product_price', 'product'];
        yield 'exact entry beats a generic root' => ['property_group_option', 'product'];
        yield 'custom entity data falls through' => ['custom_entity_blog', 'other'];
    }

    /**
     * @param array<string, string> $attributes
     */
    #[DataProvider('routeProvider')]
    public function testDomain(string $route, array $attributes, string $expected): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);
        foreach ($attributes as $key => $value) {
            $request->attributes->set($key, $value);
        }

        static::assertSame($expected, (new DomainResolver($this->registry(), new EntityGroupResolver($this->registry())))->resolve($request));
    }

    public static function routeProvider(): \Generator
    {
        yield 'storefront prefix' => ['frontend.detail.page', [], 'product'];
        yield 'store-api prefix (dashed key)' => ['store-api.product.list', [], 'product'];
        yield 'longest prefix wins' => ['frontend.checkout.cart.page', [], 'cart'];
        yield 'action segment' => ['api.action.cache.index', [], 'cache'];
        yield 'action segment (dashed key)' => ['api.action.import-export.prepare', [], 'import-export'];
        yield 'admin CRUD via entity group' => ['api.product-manufacturer.detail', ['entityName' => 'product-manufacturer'], 'product'];
        yield 'unlisted route falls through' => ['frontend.unknown.page', [], 'other'];
    }

    #[DataProvider('triggerProvider')]
    public function testTriggerGroup(string $eventName, string $expected): void
    {
        static::assertSame($expected, (new TriggerGroupResolver($this->registry()))->resolve($eventName));
    }

    public static function triggerProvider(): \Generator
    {
        yield 'longest prefix wins' => ['checkout.order.placed', 'order'];
        yield 'generic checkout prefix' => ['checkout.something', 'checkout'];
        yield 'state change prefix' => ['state_enter.order_transaction.state.paid', 'state-change'];
        yield 'unlisted event falls through' => ['some.plugin.event', 'other'];
    }

    #[DataProvider('mailProvider')]
    public function testMailGroup(?string $eventName, string $expected): void
    {
        static::assertSame($expected, (new MailGroupResolver($this->registry()))->resolve($eventName));
    }

    public static function mailProvider(): \Generator
    {
        yield 'exact entry' => ['checkout.order.placed', 'order_confirmation'];
        yield 'prefix entry' => ['newsletter.register', 'newsletter'];
        yield 'state machine rule' => ['state_enter.order_delivery.state.shipped', 'delivery_state_change'];
        yield 'no event falls through' => [null, 'other'];
    }

    #[DataProvider('numberRangeProvider')]
    public function testNumberRangeType(?string $technicalName, string $expected): void
    {
        static::assertSame($expected, (new NumberRangeTypeResolver($this->registry()))->resolve($technicalName));
    }

    public static function numberRangeProvider(): \Generator
    {
        yield 'exact entry' => ['order', 'order'];
        yield 'document types share one group' => ['document_invoice', 'document'];
        yield 'unlisted type falls through' => ['my_plugin_range', 'other'];
    }

    #[DataProvider('salesChannelTypeProvider')]
    public function testSalesChannelType(string $typeId, string $expected): void
    {
        static::assertSame($expected, (new SalesChannelTypeResolver($this->registry()))->resolve($typeId));
    }

    public static function salesChannelTypeProvider(): \Generator
    {
        yield 'storefront' => [Defaults::SALES_CHANNEL_TYPE_STOREFRONT, 'storefront'];
        yield 'api' => [Defaults::SALES_CHANNEL_TYPE_API, 'api'];
        yield 'unknown type id falls through' => ['unknown-type-id', 'other'];
    }

    #[DataProvider('taskNameProvider')]
    public function testTaskName(string $taskName, string $expected): void
    {
        static::assertSame($expected, (new TaskNameResolver($this->registry()))->resolve($taskName));
    }

    public static function taskNameProvider(): \Generator
    {
        yield 'core task passes through' => ['version.cleanup', 'version.cleanup'];
        yield 'plugin task falls through' => ['my_plugin.custom_task', 'other'];
    }

    #[DataProvider('messageProvider')]
    public function testMessageGroup(string $messageClass, string $expected): void
    {
        static::assertSame($expected, (new MessageGroupResolver($this->registry()))->resolve($messageClass));
    }

    public static function messageProvider(): \Generator
    {
        yield 'code-level rule' => [CleanupCartTask::class, 'scheduled-task'];
        yield 'unknown message falls through' => ['Swag\\Example\\Message\\DoThingMessage', 'other'];
    }

    private function registry(): LabelMappingRegistry
    {
        $registry = static::getContainer()->get(LabelMappingRegistry::class);
        static::assertInstanceOf(LabelMappingRegistry::class, $registry);

        return $registry;
    }
}
