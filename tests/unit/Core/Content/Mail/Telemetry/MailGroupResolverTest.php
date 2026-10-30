<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Telemetry\MailGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

/**
 * Resolver mechanics against a synthetic table; the shipped `mail_group` table is covered by
 * {@see \Shopware\Tests\Integration\Core\Framework\Telemetry\ShippedLabelMappingsTest}.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailGroupResolver::class)]
class MailGroupResolverTest extends TestCase
{
    #[DataProvider('eventProvider')]
    public function testResolve(?string $eventName, string $expected): void
    {
        static::assertSame($expected, $this->createResolver()->resolve($eventName));
    }

    public static function eventProvider(): \Generator
    {
        // mails sent outside a flow carry no event
        yield 'null maps to other' => [null, 'other'];
        yield 'empty string maps to other' => ['', 'other'];

        // configured entries
        yield 'exact entry' => ['checkout.order.placed', 'order_confirmation'];
        yield 'prefix entry' => ['newsletter.register', 'newsletter'];

        // the state-machine family is grouped by code, keyed on the state machine
        yield 'order state machine' => ['state_enter.order.state.completed', 'order_state_change'];
        yield 'order state machine (leave)' => ['state_leave.order.state.open', 'order_state_change'];
        yield 'delivery state machine' => ['state_enter.order_delivery.state.shipped', 'delivery_state_change'];
        yield 'transaction state machine' => ['state_enter.order_transaction.state.paid', 'payment_state_change'];
        yield 'refund state machine' => ['state_enter.order_transaction_capture_refund.state.completed', 'payment_state_change'];
        yield 'unknown state machine maps to other' => ['state_enter.custom_machine.state.x', 'other'];

        yield 'unlisted event maps to other' => ['some.plugin.event', 'other'];
    }

    public function testConfiguredEntryBeatsStateMachineRule(): void
    {
        $resolver = $this->createResolver(['state_enter.order.*' => 'custom']);

        static::assertSame('custom', $resolver->resolve('state_enter.order.state.completed'));
    }

    /**
     * @param array<string, string> $additionalEntries
     */
    private function createResolver(array $additionalEntries = []): MailGroupResolver
    {
        return new MailGroupResolver(new LabelMappingRegistry([
            MailGroupResolver::MAPPING => [
                'checkout.order.placed' => 'order_confirmation',
                'newsletter.*' => 'newsletter',
                ...$additionalEntries,
            ],
        ], 'other'));
    }
}
