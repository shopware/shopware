<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\MessengerMiddleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\MessengerMiddleware\AddAdditionalInfoForWebhooksMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * @internal
 */
#[CoversClass(AddAdditionalInfoForWebhooksMiddleware::class)]
class AddAdditionalInfoForWebhooksMiddlewareTest extends TestCase
{
    public function testMiddlewareSkipsNonWebhookEventMessages(): void
    {
        $middleware = new AddAdditionalInfoForWebhooksMiddleware();

        $envelope = new Envelope(new class {});
        $stack = new StackMiddleware([$middleware]);

        $result = $middleware->handle($envelope, $stack);

        static::assertSame($envelope, $result);
        static::assertNull($result->last(HandlerArgumentsStamp::class));
    }

    public function testMiddlewareAddsDefaultRetriesIfNoStampExists(): void
    {
        $middleware = new AddAdditionalInfoForWebhooksMiddleware();

        $message = $this->createMock(WebhookEventMessage::class);
        $envelope = new Envelope($message);

        $stack = new StackMiddleware([$middleware]);

        $result = $middleware->handle($envelope, $stack);

        /** @var HandlerArgumentsStamp|null $stamp */
        $stamp = $result->last(HandlerArgumentsStamp::class);
        static::assertNotNull($stamp);
        static::assertSame(['numRetries' => 0], $stamp->getAdditionalArguments());
    }

    public function testMiddlewareAddsRetries(): void
    {
        $middleware = new AddAdditionalInfoForWebhooksMiddleware();

        $message = $this->createMock(WebhookEventMessage::class);
        $envelope = new Envelope($message);

        $envelope = $envelope->with(new RedeliveryStamp(3));

        $stack = new StackMiddleware([$middleware]);

        $result = $middleware->handle($envelope, $stack);

        /** @var HandlerArgumentsStamp|null $stamp */
        $stamp = $result->last(HandlerArgumentsStamp::class);
        static::assertNotNull($stamp);
        static::assertSame(['numRetries' => 3], $stamp->getAdditionalArguments());
    }
}
