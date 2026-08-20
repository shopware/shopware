<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;

/**
 * @internal
 */
#[CoversClass(WebhookActivationTrigger::class)]
class WebhookActivationTriggerTest extends TestCase
{
    public function testValues(): void
    {
        static::assertSame(
            ['trial', 'idle', 'manual', 'app_reset'],
            array_column(WebhookActivationTrigger::cases(), 'value'),
        );
    }
}
