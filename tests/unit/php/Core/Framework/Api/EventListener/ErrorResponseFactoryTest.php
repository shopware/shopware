<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory
 */
class ErrorResponseFactoryTest extends TestCase
{
    public function testStackTraceForExceptionInDebugMode(): void
    {
        $factory = new ErrorResponseFactory();

        /* @var \Symfony\Component\HttpFoundation\JsonResponse $response */
        $response = $factory->getResponseFromException(new \Exception('test'), true);
        $data = null;
        if ($response->getContent()) {
            $data = json_decode($response->getContent(), true);
        }
        var_dump($data['stack']);
        static::assertArrayHasKey('stack', $data);
        static::assertIsArray($data['stack']);
    }

    public function testNoStackTraceForExceptionNotInDebugMode(): void
    {
        $factory = new ErrorResponseFactory();

        /* @var \Symfony\Component\HttpFoundation\JsonResponse $response */
        $response = $factory->getResponseFromException(new \Exception('test'), false);
        $data = null;
        if ($response->getContent()) {
            $data = json_decode($response->getContent(), true);
        }
        static::assertNull($data['stack']);
    }
}
