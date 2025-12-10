<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\DTO\Consent;
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
        $event = new ConsentAcceptedEvent(
            new Consent(Uuid::randomHex(), 'my-consent', ConsentScope::ADMIN_USER, new \DateTimeImmutable(), null),
            'user-123'
        );

        static::assertSame('my-consent', $event->consent->name);
        static::assertSame('user-123', $event->identifier);
    }
}
