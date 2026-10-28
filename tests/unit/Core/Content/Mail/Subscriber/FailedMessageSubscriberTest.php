<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Subscriber\FailedMessageSubscriber;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FailedMessageSubscriber::class)]
class FailedMessageSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = FailedMessageSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(FailedMessageEvent::class, $events);
        static::assertSame('logEvent', $events[FailedMessageEvent::class]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    #[DataProvider('messageProvider')]
    public function testLogEvent(RawMessage $message, array $metadata): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('insert')
            ->with(
                static::equalTo('log_entry'),
                static::callback(static function (array $entry) use ($metadata) {
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
                    static::assertSame([
                        ...$metadata,
                        'exceptionClass' => \Exception::class,
                        'exceptionCode' => 0,
                    ], $context);
                    static::assertNull($entry['extra']);
                    static::assertStringNotContainsString('Test Message', $entry['context']);
                    static::assertStringNotContainsString('Test Exception', $entry['context']);

                    return true;
                })
            );

        $subscriber = new FailedMessageSubscriber($connection, new NativeClock());

        $event = new FailedMessageEvent(
            $message,
            new \Exception('Test Exception')
        );

        $subscriber->logEvent($event);
    }

    /**
     * @return \Generator<string, array{RawMessage, array<string, mixed>}>
     */
    public static function messageProvider(): \Generator
    {
        yield 'raw message logs no content' => [new RawMessage('Test Message'), []];

        $email = (new Email())->subject('Recovery')->to('customer@example.com')->text('Test Message')->html('Test Message');
        $metadata = ['subject' => 'Recovery', 'recipients' => ['customer@example.com' => '']];

        yield 'email logs metadata only' => [$email, $metadata];
        yield 'signed MIME message retains header metadata' => [new Message($email->getHeaders(), $email->getBody()), $metadata];
    }
}
