<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentAcceptedEvent::class)]
class ConsentAcceptedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ConsentAcceptedEvent('test-consent', 'user-123');

        static::assertSame('test-consent', $event->consentName);
        static::assertSame('user-123', $event->identifier);
    }
}
