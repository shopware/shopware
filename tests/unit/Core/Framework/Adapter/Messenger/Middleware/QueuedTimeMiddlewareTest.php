<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Messenger\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Messenger\Middleware\QueuedTimeMiddleware;
use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(QueuedTimeMiddleware::class)]
class QueuedTimeMiddlewareTest extends TestCase
{
    public function testAddsSentAtStampIfNonePresent(): void
    {
        $middleware = new QueuedTimeMiddleware();
        $envelope = new Envelope(new \stdClass());

        $resultingEnvelope = $middleware->handle($envelope, $this->prepareStack());
        static::assertInstanceOf(SentAtStamp::class, $resultingEnvelope->last(SentAtStamp::class));
    }

    public function testDoesNotAddSentAtStampIfAlreadyPresent(): void
    {
        $sentAt = new \DateTimeImmutable('@123456789');
        $middleware = new QueuedTimeMiddleware();
        $envelope = new Envelope(new \stdClass(), [new SentAtStamp($sentAt)]);

        $resultingEnvelope = $middleware->handle($envelope, $this->prepareStack());
        static::assertInstanceOf(SentAtStamp::class, $resultingEnvelope->last(SentAtStamp::class));
        static::assertCount(1, $resultingEnvelope->all(SentAtStamp::class));
        static::assertSame($sentAt, $resultingEnvelope->last(SentAtStamp::class)->getSentAt());
    }

    public function testDoesNotAddSentAtStampIfInReceiveStage(): void
    {
        $middleware = new QueuedTimeMiddleware();
        $envelope = new Envelope(new \stdClass(), [new ReceivedStamp('TestTransport')]);

        $resultingEnvelope = $middleware->handle($envelope, $this->prepareStack());
        static::assertNull($resultingEnvelope->last(SentAtStamp::class));
    }

    private function prepareStack(): StackInterface
    {
        $stack = $this->createMock(StackInterface::class);
        $middlewareMock = $this->createMock(MiddlewareInterface::class);

        $stack->expects($this->once())
            ->method('next')
            ->willReturn($middlewareMock);

        // checking if middleware mock will be called with proper envelope
        $middlewareMock->expects($this->once())
            ->method('handle')
            ->willReturnArgument(0);

        return $stack;
    }
}
