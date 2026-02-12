<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentScope;
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
            'my-consent',
            ConsentScope\AdminUser::NAME,
            'consent-identifier',
            'user-123'
        );

        static::assertSame('my-consent', $event->consentName);
        static::assertSame(ConsentScope\AdminUser::NAME, $event->consentScope);
        static::assertSame('consent-identifier', $event->identifier);
        static::assertSame('user-123', $event->actor);
    }
}
