<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopware\Core\System\Consent\Log\ConsentChangedSubscriber;
use Shopware\Core\System\Consent\Log\ConsentLogInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentChangedSubscriber::class)]
class ConsentChangedSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $events = ConsentChangedSubscriber::getSubscribedEvents();

        static::assertEquals([
            ConsentAcceptedEvent::class => 'onConsentAccepted',
            ConsentRevokedEvent::class => 'onConsentRevoked',
        ], $events);
    }

    public function testConsentAccepted(): void
    {
        $logger = $this->createMock(ConsentLogInterface::class);
        $logger->method('log')->with(
            ConsentStatus::ACCEPTED,
            'test-consent',
            'identifier-123',
            'actor-456'
        );

        $event = new ConsentAcceptedEvent(
            'test-consent',
            ConsentScope\System::NAME,
            'identifier-123',
            'actor-456'
        );

        $subscriber = new ConsentChangedSubscriber($logger);
        $subscriber->onConsentAccepted($event);
    }

    public function testConsentRevoked(): void
    {
        $logger = $this->createMock(ConsentLogInterface::class);
        $logger->method('log')->with(
            ConsentStatus::REVOKED,
            'test-consent',
            'identifier-123',
            'actor-456'
        );

        $event = new ConsentRevokedEvent(
            'test-consent',
            ConsentScope\System::NAME,
            'identifier-123',
            'actor-456'
        );

        $subscriber = new ConsentChangedSubscriber($logger);
        $subscriber->onConsentRevoked($event);
    }
}
