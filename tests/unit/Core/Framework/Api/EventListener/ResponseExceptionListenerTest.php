<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use Doctrine\DBAL\Driver\PDO\Exception as PDOException;
use Doctrine\DBAL\Exception\ConnectionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Exception\CustomerAuthThrottledException;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Framework\Api\EventListener\ResponseExceptionListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiterException;
use Shopware\Core\SalesChannelRequest;
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
    private const DRIVER_MESSAGE = 'SQLSTATE[HY000] [1045] Access denied for user \'dbuser\'@\'10.0.0.5\' (using password: YES)';

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

    #[DataProvider('wrappedRateLimitProvider')]
    public function testWrappedRateLimitResponseTellsTheClientWhenToRetry(\Throwable $exception): void
    {
        $event = $this->createEvent($exception);
        (new ResponseExceptionListener())->onKernelException($event);

        static::assertSame('30', $event->getResponse()?->headers->get('Retry-After'));
    }

    public static function wrappedRateLimitProvider(): \Generator
    {
        yield 'login, wrapping the rate limit exception' => [new CustomerAuthThrottledException(30, RateLimiterException::limitExceeded(time() + 30))];
        yield 'document, without the rate limit exception' => [DocumentException::documentAuthThrottledException(30)];
    }

    public function testOtherErrorsHaveNoRetryHeader(): void
    {
        $event = $this->createEvent(new \RuntimeException('failed'));
        (new ResponseExceptionListener())->onKernelException($event);

        static::assertFalse($event->getResponse()?->headers->has('Retry-After'));
    }

    public function testConnectionErrorRendersGenericErrorPageForHtmlRequestsWithoutDebugMode(): void
    {
        $event = $this->createEvent($this->createConnectionException(), 'text/html');

        (new ResponseExceptionListener(false))->onKernelException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(500, $response->getStatusCode());
        static::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        static::assertStringContainsString('<h1>Oops! An Error Occurred</h1>', (string) $response->getContent());
        static::assertStringNotContainsString('dbuser', (string) $response->getContent());
        static::assertStringNotContainsString('10.0.0.5', (string) $response->getContent());
    }

    public function testConnectionErrorRendersGenericErrorPageWithoutAcceptHeader(): void
    {
        $event = $this->createEvent($this->createConnectionException());

        (new ResponseExceptionListener(false))->onKernelException($event);

        static::assertStringContainsString('<h1>Oops! An Error Occurred</h1>', (string) $event->getResponse()?->getContent());
    }

    public function testConnectionErrorIsHiddenForJsonRequestsWithoutDebugMode(): void
    {
        $event = $this->createEvent($this->createConnectionException(), 'application/json');

        (new ResponseExceptionListener(false))->onKernelException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(500, $response->getStatusCode());
        static::assertSame([
            'errors' => [
                [
                    'code' => '0',
                    'status' => '500',
                    'title' => 'Internal Server Error',
                    'detail' => 'Internal Server Error',
                ],
            ],
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testConnectionErrorIsHiddenForJsonApiRequestsWithoutDebugMode(): void
    {
        $event = $this->createEvent($this->createConnectionException(), 'application/vnd.api+json');

        (new ResponseExceptionListener(false))->onKernelException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(500, $response->getStatusCode());
        static::assertSame([
            'errors' => [
                [
                    'code' => '0',
                    'status' => '500',
                    'title' => 'Internal Server Error',
                    'detail' => 'Internal Server Error',
                ],
            ],
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testConnectionErrorIsShownInDebugMode(): void
    {
        $exception = $this->createConnectionException();
        $previous = $exception->getPrevious();
        static::assertNotNull($previous);
        $event = $this->createEvent($exception);

        (new ResponseExceptionListener(true))->onKernelException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(500, $response->getStatusCode());
        static::assertSame([
            'errors' => [
                [
                    'code' => '1045',
                    'status' => '500',
                    'title' => 'Internal Server Error',
                    'detail' => $exception->getMessage(),
                    'meta' => [
                        'trace' => json_decode((string) json_encode($exception->getTrace()), true, 512, \JSON_THROW_ON_ERROR),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'previous' => [
                            [
                                'code' => '1045',
                                'status' => '500',
                                'title' => 'Internal Server Error',
                                'detail' => self::DRIVER_MESSAGE,
                                'meta' => [
                                    'trace' => json_decode((string) json_encode($previous->getTrace()), true, 512, \JSON_THROW_ON_ERROR),
                                    'file' => $previous->getFile(),
                                    'line' => $previous->getLine(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testOtherExceptionsKeepTheirMessage(): void
    {
        $event = $this->createEvent(new \RuntimeException('Something went wrong'));

        (new ResponseExceptionListener(false))->onKernelException($event);

        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(500, $response->getStatusCode());
        static::assertSame([
            'errors' => [
                [
                    'code' => '0',
                    'status' => '500',
                    'title' => 'Internal Server Error',
                    'detail' => 'Something went wrong',
                ],
            ],
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testSalesChannelRequestsAreLeftToTheStorefront(): void
    {
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);
        $event = new ExceptionEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $this->createConnectionException());

        (new ResponseExceptionListener(false))->onKernelException($event);

        static::assertNull($event->getResponse());
    }

    private function createConnectionException(): ConnectionException
    {
        return new ConnectionException(new PDOException(self::DRIVER_MESSAGE, null, 1045), null);
    }

    private function createEvent(\Throwable $exception, ?string $accept = null): ExceptionEvent
    {
        $request = new Request();
        if ($accept !== null) {
            $request->headers->set('Accept', $accept);
        }

        return new ExceptionEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
