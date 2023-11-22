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

/**
 * @internal
 */
#[CoversClass(MailSentEvent::class)]
class MailSentEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new MailSentEvent(
            'my-subject',
            ['foo' => 'bar'],
            ['mixed' => 'content'],
            Context::createDefaultContext()
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('subject', $flow->data());
        static::assertArrayHasKey('contents', $flow->data());
        static::assertArrayHasKey('recipients', $flow->data());

        static::assertEquals('my-subject', $flow->data()['subject']);
        static::assertEquals(['foo' => 'bar'], $flow->data()['recipients']);
        static::assertEquals(['mixed' => 'content'], $flow->data()['contents']);
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
}
