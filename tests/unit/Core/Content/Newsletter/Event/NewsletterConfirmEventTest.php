<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterConfirmEvent::class)]
class NewsletterConfirmEventTest extends TestCase
{
    public function testMailStructAddressesTheRecipientByFullName(): void
    {
        $recipient = new NewsletterRecipientEntity();
        $recipient->setEmail('jane@example.com');
        $recipient->setFirstName('Jane');
        $recipient->setLastName('Doe');

        $event = new NewsletterConfirmEvent(Context::createDefaultContext(), $recipient, 'sales-channel-id');

        static::assertSame(['jane@example.com' => 'Jane Doe'], $event->getMailStruct()->getRecipients());

        // the struct is a snapshot taken on first access: later recipient changes must not leak into it
        $recipient->setFirstName('Changed');

        static::assertSame(['jane@example.com' => 'Jane Doe'], $event->getMailStruct()->getRecipients());
    }

    public function testExposesItsPayload(): void
    {
        $recipient = new NewsletterRecipientEntity();
        $recipient->setId('recipient-id');
        $context = Context::createDefaultContext();

        $event = new NewsletterConfirmEvent($context, $recipient, 'sales-channel-id');

        static::assertSame(NewsletterConfirmEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($recipient, $event->getNewsletterRecipient());
        static::assertSame('recipient-id', $event->getNewsletterRecipientId());
        static::assertSame('sales-channel-id', $event->getSalesChannelId());
        static::assertSame(['newsletterRecipient'], array_keys(NewsletterConfirmEvent::getAvailableData()->toArray()));
    }
}
