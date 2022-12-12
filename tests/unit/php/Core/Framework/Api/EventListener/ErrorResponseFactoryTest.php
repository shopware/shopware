<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory
 */
class ErrorResponseFactoryTest extends TestCase
{
    public function testStackTraceForExceptionInDebugMode(): void
    {
        $message = 'this is an error';
        $factory = new ErrorResponseFactory();

        /* @var JsonResponse $response */
        $response = $factory->getResponseFromException(new \Exception($message), true);
        $data = null;
        if ($response->getContent()) {
            $data = json_decode($response->getContent(), true);
        }

        $errors = $data['errors'];
        static::assertCount(1, $errors);
        static::assertSame($message, $errors[0]['detail']);

        $stack = $data['stack'];
        static::assertSame(self::class, $stack[0]['class']);
        static::assertSame('testStackTraceForExceptionInDebugMode', $stack[0]['function']);

        static::assertSame(TestCase::class, $stack[1]['class']);
        static::assertSame('runTest', $stack[1]['function']);

        static::assertSame(TestCase::class, $stack[2]['class']);
        static::assertSame('runBare', $stack[2]['function']);

        static::assertCount(12, $stack);
    }

    public function testNoStackTraceForExceptionNotInDebugMode(): void
    {
        $factory = new ErrorResponseFactory();

        /* @var JsonResponse $response */
        $response = $factory->getResponseFromException(new \Exception('test'));
        $data = null;
        if ($response->getContent()) {
            $data = json_decode($response->getContent(), true);
        }
        static::assertNull($data['stack']);
    }
}
