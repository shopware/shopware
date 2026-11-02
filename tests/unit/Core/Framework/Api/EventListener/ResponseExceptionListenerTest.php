<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\ResponseExceptionListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiterException;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResponseExceptionListener::class)]
class ResponseExceptionListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
    }

    public function testRateLimitResponseTellsTheClientWhenToRetry(): void
    {
        $clock = new MockClock('2026-11-02 10:00:00');
        Clock::set($clock);

        $event = $this->createEvent(RateLimiterException::limitExceeded($clock->now()->getTimestamp() + 30));
        (new ResponseExceptionListener())->onKernelException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        static::assertSame('30', $response->headers->get('Retry-After'));
    }

    public function testOtherErrorsHaveNoRetryHeader(): void
    {
        $event = $this->createEvent(new \RuntimeException('failed'));
        (new ResponseExceptionListener())->onKernelException($event);

        static::assertFalse($event->getResponse()?->headers->has('Retry-After'));
    }

    private function createEvent(\Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(static::createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
