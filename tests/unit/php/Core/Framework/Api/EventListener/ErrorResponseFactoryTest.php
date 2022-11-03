<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory
 */
class ErrorResponseFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testStackTraceForExceptionInDebugMode(): void
    {
        $factory = new ErrorResponseFactory();

        /* @var \Symfony\Component\HttpFoundation\JsonResponse $response */
        $response = $factory->getResponseFromException(new \Exception('test'), true);
        $data = json_decode($response->getContent());
        static::assertArrayHasKey('stack', $data);
        static::assertIsArray($data['stack']);
    }

    public function testNoStackTraceForExceptionNotInDebugMode(): void
    {
        $factory = new ErrorResponseFactory();

        /* @var \Symfony\Component\HttpFoundation\JsonResponse $response */
        $response = $factory->getResponseFromException(new \Exception('test'), false);
        $data = json_decode($response->getContent());
        static::assertNull($data['stack']);
    }
}
