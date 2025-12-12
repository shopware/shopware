<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentRevokedEvent::class)]
class ConsentRevokedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ConsentRevokedEvent(
            'my-consent',
            ConsentScope::ADMIN_USER,
            'user-456'
        );

        static::assertSame('my-consent', $event->consentName);
        static::assertSame(ConsentScope::ADMIN_USER, $event->consentScope);
        static::assertSame('user-456', $event->identifier);
    }
}
