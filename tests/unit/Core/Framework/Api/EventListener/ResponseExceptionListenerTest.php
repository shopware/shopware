<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use Doctrine\DBAL\Driver\PDO\Exception as PDOException;
use Doctrine\DBAL\Exception\ConnectionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\ResponseExceptionListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;
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
