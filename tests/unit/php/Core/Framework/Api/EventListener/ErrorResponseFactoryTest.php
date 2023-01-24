<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Annotation\DocBlock;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory
 */
class ErrorResponseFactoryTest extends TestCase
{
    /**
     * @dataProvider getResponseFromExceptionProvider
     */
    public function testStackTraceForExceptionInDebugMode(\Exception $exception): void
    {
        $factory = new ErrorResponseFactory();

        /* @var JsonResponse $response */
        $response = $factory->getResponseFromException($exception, true);

        $data = null;
        if ($response->getContent()) {
            $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        }

        $errors = $data['errors'];
        static::assertCount(1, $errors);
        static::assertSame($exception->getMessage(), $errors[0]['detail']);

        $stack = $exception instanceof ShopwareHttpException
            ? $data['errors'][0]['trace']
            : $data['errors'][0]['meta']['trace'];

        static::assertSame(self::class, $stack[0]['class']);
        static::assertSame('getResponseFromExceptionProvider', $stack[0]['function']);

        static::assertSame(DocBlock::class, $stack[1]['class']);
        static::assertSame('getDataFromDataProviderAnnotation', $stack[1]['function']);

        static::assertSame(DocBlock::class, $stack[2]['class']);
        static::assertSame('getProvidedData', $stack[2]['function']);
    }

    /**
     * @dataProvider getResponseFromExceptionProvider
     */
    public function testNoStackTraceForExceptionNotInDebugMode(\Exception $exception): void
    {
        $factory = new ErrorResponseFactory();

        /* @var JsonResponse $response */
        $response = $factory->getResponseFromException(new \Exception('test'));
        $data = null;
        if ($response->getContent()) {
            $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        }

        if ($exception instanceof ShopwareHttpException) {
            static::assertArrayNotHasKey('trace', $data['errors'][0]);

            return;
        }

        static::assertArrayNotHasKey('meta', $data['errors'][0]);
        if (isset($data['errors'][0]['meta']['trace'])) {
            static::assertArrayNotHasKey('trace', $data['errors'][0]['meta']);
        }
    }

    public function getResponseFromExceptionProvider(): \Generator
    {
        $message = 'this is an error';

        yield 'exception' => [new \Exception($message)];
        yield 'http exception' => [new HttpException(500)];
        yield 'shopware http exception' => [new PageNotFoundException($message)];
    }
}
