<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterUnsubscribeEvent::class)]
class NewsletterUnsubscribeEventTest extends TestCase
{
    public function testMailStructAddressesTheRecipientByFullName(): void
    {
        $recipient = new NewsletterRecipientEntity();
        $recipient->setEmail('jane@example.com');
        $recipient->setFirstName('Jane');
        $recipient->setLastName('Doe');

        $event = new NewsletterUnsubscribeEvent(Context::createDefaultContext(), $recipient, 'sales-channel-id');

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

        $event = new NewsletterUnsubscribeEvent($context, $recipient, 'sales-channel-id');

        static::assertSame(NewsletterUnsubscribeEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($recipient, $event->getNewsletterRecipient());
        static::assertSame('recipient-id', $event->getNewsletterRecipientId());
        static::assertSame('sales-channel-id', $event->getSalesChannelId());
        static::assertSame(['newsletterRecipient'], array_keys(NewsletterUnsubscribeEvent::getAvailableData()->toArray()));
    }
}
