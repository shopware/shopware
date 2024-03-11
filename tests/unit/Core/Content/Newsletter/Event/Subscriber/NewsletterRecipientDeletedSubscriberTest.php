<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\Event\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexingMessage;
use Shopware\Core\Content\Newsletter\Event\Subscriber\NewsletterRecipientDeletedSubscriber;
use Shopware\Core\Content\Newsletter\NewsletterEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(NewsletterRecipientDeletedSubscriber::class)]
class NewsletterRecipientDeletedSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [NewsletterEvents::NEWSLETTER_RECIPIENT_DELETED_EVENT => 'onNewsletterRecipientDeleted'],
            NewsletterRecipientDeletedSubscriber::getSubscribedEvents()
        );
    }

    public function testOnNewsletterRecipientDeleted(): void
    {
        $event = $this->createMock(EntityDeletedEvent::class);
        $event->method('getIds')->willReturn(['id1', 'id2']);
        $event->method('getContext')->willReturn(Context::createDefaultContext());

        $message = new NewsletterRecipientIndexingMessage(['id1', 'id2'], null, Context::createDefaultContext());
        $message->setDeletedNewsletterRecipients(true);
        $message->setIndexer('newsletter_recipient.indexer');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(static::once())->method('dispatch')->with($message)->willReturn(Envelope::wrap($message));

        $subscriber = new NewsletterRecipientDeletedSubscriber($messageBus);
        $subscriber->onNewsletterRecipientDeleted($event);
    }
}
