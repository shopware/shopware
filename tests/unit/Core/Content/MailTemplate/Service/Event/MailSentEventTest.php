<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailSentEvent::class)]
class MailSentEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new MailSentEvent(
            'my-subject',
            ['foo' => 'bar'],
            ['text/plain' => 'content'],
            Context::createDefaultContext()
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('subject', $flow->data());
        static::assertArrayHasKey('contents', $flow->data());
        static::assertArrayHasKey('recipients', $flow->data());

        static::assertSame('my-subject', $flow->data()['subject']);
        static::assertSame(['foo' => 'bar'], $flow->data()['recipients']);
        static::assertSame(['text/plain' => 'content'], $flow->data()['contents']);
    }

    public function testInstantiate(): void
    {
        $context = Context::createDefaultContext();

        $event = new MailSentEvent(
            'mail test',
            [
                'john.doe@example.com' => 'John doe',
                'jane.doe@example.com' => 'Jane doe',
            ],
            [
                'text/plain' => 'This is a plain text',
                'text/html' => 'This is a html text',
            ],
            $context,
            CheckoutOrderPlacedEvent::EVENT_NAME,
        );

        static::assertSame([
            'john.doe@example.com' => 'John doe',
            'jane.doe@example.com' => 'Jane doe',
        ], $event->getRecipients());
        static::assertSame(Level::Info, $event->getLogLevel());
        static::assertSame('mail test', $event->getSubject());
        static::assertSame([
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'subject' => 'mail test',
            'recipients' => [
                'john.doe@example.com' => 'John doe',
                'jane.doe@example.com' => 'Jane doe',
            ],
            'contents' => [
                'text/plain' => 'This is a plain text',
                'text/html' => 'This is a html text',
            ],
        ], $event->getLogData());
        static::assertSame('mail.sent', $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame([
            'text/plain' => 'This is a plain text',
            'text/html' => 'This is a html text',
        ], $event->getContents());
    }

    public function testAvailableDataDescribesTheFlowPayload(): void
    {
        static::assertSame(['subject', 'contents', 'recipients'], array_keys(MailSentEvent::getAvailableData()->toArray()));
    }

    public function testCarriesTheMailDataTemplateDataAndMessage(): void
    {
        $mail = new Email();

        $event = new MailSentEvent(
            'subject',
            ['john.doe@example.com' => 'John doe'],
            ['text/plain' => 'plain'],
            Context::createDefaultContext(),
            'checkout.order.placed',
            ['templateId' => 'template-id', 'salesChannelId' => 'sales-channel-id'],
            ['order' => ['orderNumber' => '10001']],
            $mail,
        );

        static::assertSame('template-id', $event->getTemplateId());
        static::assertSame('sales-channel-id', $event->getSalesChannelId());
        static::assertSame('checkout.order.placed', $event->getEventName());
        static::assertSame(['order' => ['orderNumber' => '10001']], $event->getTemplateData());
        static::assertSame($mail, $event->getMessage());
        // the template data is not part of the flow payload, as it can contain entities
        static::assertSame(['subject', 'contents', 'recipients'], array_keys($event->getValues()));
    }

    public function testNewDataIsOptional(): void
    {
        $event = new MailSentEvent('subject', ['a@b.c' => null], [], Context::createDefaultContext());

        static::assertSame([], $event->getData());
        static::assertSame([], $event->getTemplateData());
        static::assertNull($event->getMessage());
        static::assertNull($event->getEventName());
        static::assertNull($event->getTemplateId());
        static::assertNull($event->getSalesChannelId());
    }
}
