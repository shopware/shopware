<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieConsentLog\CookieConsentLogEntity;
use Shopware\Core\Content\Cookie\Event\CookieConsentLoggedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieConsentLoggedEvent::class)]
class CookieConsentLoggedEventTest extends TestCase
{
    public function testWebhookContract(): void
    {
        $event = $this->createEvent();

        static::assertSame('cookie.consent.logged', $event->getName());
        static::assertSame([
            'consentAction' => 'accept_selected',
            'groupDecisions' => ['cookie.groupStatistical' => CookieConsentLogEntity::DECISION_PARTIAL],
            'acceptedCookies' => ['lorem'],
            'serverConfigHash' => 'server-hash',
            'renderedConfigHash' => 'rendered-hash',
            'salesChannelId' => 'sales-channel-id',
            'languageId' => 'language-id',
        ], $event->getWebhookPayload());
    }

    public function testIsOnlyAllowedForAppsThatMayReadTheLogEntity(): void
    {
        $event = $this->createEvent();

        static::assertTrue($event->isAllowed('app-id', new AclPrivilegeCollection(['cookie_consent_log:read'])));
        static::assertFalse($event->isAllowed('app-id', new AclPrivilegeCollection(['cookie_consent_log:update'])));
        static::assertFalse($event->isAllowed('app-id', new AclPrivilegeCollection([])));
    }

    private function createEvent(): CookieConsentLoggedEvent
    {
        return new CookieConsentLoggedEvent(
            consentAction: 'accept_selected',
            groupDecisions: ['cookie.groupStatistical' => CookieConsentLogEntity::DECISION_PARTIAL],
            acceptedCookies: ['lorem'],
            serverConfigHash: 'server-hash',
            renderedConfigHash: 'rendered-hash',
            salesChannelId: 'sales-channel-id',
            languageId: 'language-id',
        );
    }
}
