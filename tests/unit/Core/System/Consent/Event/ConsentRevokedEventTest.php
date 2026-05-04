<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
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
            ConsentScope\AdminUser::NAME,
            'consent-identifier',
            'user-456'
        );

        static::assertSame('my-consent', $event->consentName);
        static::assertSame(ConsentScope\AdminUser::NAME, $event->consentScope);
        static::assertSame('consent-identifier', $event->identifier);
        static::assertSame('user-456', $event->actor);
        static::assertSame('consent.my-consent.revoked', $event->getName());
        static::assertSame([
            'consentName' => 'my-consent',
            'consentScope' => ConsentScope\AdminUser::NAME,
            'identifier' => 'consent-identifier',
        ], $event->getWebhookPayload());
        static::assertTrue($event->isAllowed('app-id', new AclPrivilegeCollection(['consent:my-consent:read'])));
        static::assertFalse($event->isAllowed('app-id', new AclPrivilegeCollection(['consent:other-consent:read'])));
    }
}
