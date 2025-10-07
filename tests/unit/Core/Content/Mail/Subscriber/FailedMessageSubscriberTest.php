<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Subscriber\FailedMessageSubscriber;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[CoversClass(FailedMessageSubscriber::class)]
class FailedMessageSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = FailedMessageSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(FailedMessageEvent::class, $events);
        static::assertSame('logEvent', $events[FailedMessageEvent::class]);
    }

    public function testLogEvent(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with(
                static::equalTo('log_entry'),
                static::callback(function (array $entry) {
                    static::assertArrayHasKey('id', $entry);
                    static::assertArrayHasKey('message', $entry);
                    static::assertArrayHasKey('level', $entry);
                    static::assertArrayHasKey('channel', $entry);
                    static::assertArrayHasKey('context', $entry);
                    static::assertArrayHasKey('extra', $entry);
                    static::assertArrayHasKey('created_at', $entry);

                    static::assertSame('mail.message.failed', $entry['message']);
                    static::assertSame('mail', $entry['channel']);

                    $context = json_decode($entry['context'], true);
                    static::assertSame('Test Message', $context['rawMessage']);
                    static::assertSame('Test Exception', $context['error']);

                    $extra = json_decode($entry['extra'], true);
                    static::assertIsArray($extra);
                    static::assertArrayHasKey('exception', $extra);
                    static::assertArrayHasKey('trace', $extra);

                    return true;
                })
            );

        $subscriber = new FailedMessageSubscriber($connection);

        $event = new FailedMessageEvent(
            new RawMessage('Test Message'),
            new \Exception('Test Exception')
        );

        $subscriber->logEvent($event);
    }
}
