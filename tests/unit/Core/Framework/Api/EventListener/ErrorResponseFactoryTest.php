<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Annotation\DocBlock;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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

    public static function getResponseFromExceptionProvider(): \Generator
    {
        $message = 'this is an error';

        yield 'exception' => [new \Exception($message)];
        yield 'http exception' => [new HttpException(500)];
        yield 'shopware http exception' => [new PageNotFoundException($message)];
    }

    public function testItTransformsRegularExceptionsToJson(): void
    {
        $exceptionDetail = 'this is a regular exception';

        $errorResponseFactory = new ErrorResponseFactory();
        $response = $errorResponseFactory->getResponseFromException(new \Exception($exceptionDetail, 5));
        $responseBody = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(500, $response->getStatusCode());
        static::assertEquals([
            'errors' => [
                [
                    'code' => '5',
                    'status' => '500',
                    'title' => 'Internal Server Error',
                    'detail' => $exceptionDetail,
                ],
            ],
        ], $responseBody);
    }

    public function testItOverridesWithStatusCodeFromHttpException(): void
    {
        $exceptionDetail = 'this is a regular exception';

        $errorResponseFactory = new ErrorResponseFactory();
        $response = $errorResponseFactory->getResponseFromException(new HttpException(418, $exceptionDetail));

        $responseBody = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(418, $response->getStatusCode());
        static::assertEquals([
            'errors' => [
                [
                    'code' => '0',
                    'status' => '418',
                    'title' => Response::$statusTexts[418],
                    'detail' => $exceptionDetail,
                ],
            ],
        ], $responseBody);
    }

    public function testItResolvesExceptionsRecursive(): void
    {
        $exceptionDetail = 'this is a regular exception';

        $errorResponseFactory = new ErrorResponseFactory();
        $response = $errorResponseFactory->getResponseFromException(new HttpException(418, $exceptionDetail, new HttpException(500, 'im nested')), true);

        $responseBody = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $meta = $responseBody['errors'][0]['meta'];
        unset($meta['previous'][0]['meta']);

        static::assertNotNull($meta);
        static::assertEquals([
            [
                'code' => '0',
                'status' => '500',
                'title' => Response::$statusTexts[500],
                'detail' => 'im nested',
            ],
        ], $meta['previous']);

        unset($responseBody['errors'][0]['meta']);
        static::assertEquals(418, $response->getStatusCode());
        static::assertEquals([
            [
                'code' => '0',
                'status' => '418',
                'title' => Response::$statusTexts[418],
                'detail' => $exceptionDetail,
            ],
        ], $responseBody['errors']);
    }

    public function testItUnwindsShopwareHttpException(): void
    {
        $params = [
            'paramOne' => '1',
            'paramTwo' => '2',
        ];

        $simpleHttpException = new SimpleShopwareHttpException($params);
        $errorResponseFactory = new ErrorResponseFactory();
        $response = $errorResponseFactory->getResponseFromException($simpleHttpException);
        $responseBody = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(418, $response->getStatusCode());
        static::assertEquals([
            'errors' => [
                [
                    'code' => SimpleShopwareHttpException::EXCEPTION_CODE,
                    'status' => '418',
                    'title' => Response::$statusTexts[Response::HTTP_I_AM_A_TEAPOT],
                    'detail' => 'this is param 1: 1 and this is param 2: 2',
                    'meta' => [
                        'parameters' => $params,
                    ],
                ],
            ],
        ], $responseBody);
    }

    public function testWriteExceptionConvertsNormalExceptionCorrectly(): void
    {
        $errorResponseFactory = new ErrorResponseFactory();
        $normalException = new \Exception('this is regular exception');

        $errorFromWrite = $errorResponseFactory->getResponseFromException((new WriteException())->add($normalException));
        $errorRaw = $errorResponseFactory->getResponseFromException($normalException);

        static::assertEquals($errorFromWrite->getContent(), $errorRaw->getContent());
    }

    public function testWriteExceptionConvertsHttpExceptionCorrectly(): void
    {
        $errorResponseFactory = new ErrorResponseFactory();
        $httpException = new HttpException(418, 'with other message');

        $errorFromWrite = $errorResponseFactory->getResponseFromException((new WriteException())->add($httpException));
        $errorRaw = $errorResponseFactory->getResponseFromException($httpException);

        static::assertEquals($errorFromWrite->getContent(), $errorRaw->getContent());
    }

    public function testWriteExceptionConvertsShopwareHttpExceptionCorrectly(): void
    {
        $errorResponseFactory = new ErrorResponseFactory();

        $shopwareHttpException = new SimpleShopwareHttpException(['paramOne' => 1, 'paramTwo' => 2]);
        $errorFromWrite = $errorResponseFactory->getResponseFromException((new WriteException())->add($shopwareHttpException));
        $errorRaw = $errorResponseFactory->getResponseFromException($shopwareHttpException);

        static::assertEquals($errorFromWrite->getContent(), $errorRaw->getContent());
    }

    public function testYieldDoesNotOverrideErrors(): void
    {
        $simpleShopwareHttpException = new SimpleShopwareHttpException(['paramOne' => 1, 'paramTwo' => 2]);
        $writeException = (new WriteException())
            ->add(
                (new WriteException())
                    ->add($simpleShopwareHttpException)
                    ->add($simpleShopwareHttpException)
            )->add(
                (new WriteException())
                    ->add($simpleShopwareHttpException)
                    ->add($simpleShopwareHttpException)
            );

        $errorResponseFactory = new ErrorResponseFactory();
        $response = $errorResponseFactory->getResponseFromException($writeException);
        $convertedShopwareHttpException = $errorResponseFactory->getErrorsFromException($simpleShopwareHttpException)[0];

        $responseBody = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(4, $responseBody['errors']);
        static::assertEquals([
            $convertedShopwareHttpException,
            $convertedShopwareHttpException,
            $convertedShopwareHttpException,
            $convertedShopwareHttpException,
        ], $responseBody['errors']);
    }

    /**
     * @return list<array<string, string>>
     */
    public static function invalidUtf8SequencesProvider(): array
    {
        return [
            ['Invalid 2 Octet Sequence' => "\xc3\x28"],
            ['Invalid Sequence Identifier' => "\xa0\xa1"],
            ['Invalid 3 Octet Sequence (in 2nd Octet)' => "\xe2\x28\xa1"],
            ['Invalid 3 Octet Sequence (in 3rd Octet)' => "\xe2\x82\x28"],
            ['Invalid 4 Octet Sequence (in 2nd Octet)' => "\xf0\x28\x8c\xbc"],
            ['Invalid 4 Octet Sequence (in 3rd Octet)' => "\xf0\x90\x28\xbc"],
            ['Invalid 4 Octet Sequence (in 4th Octet)' => "\xf0\x28\x8c\x28"],
        ];
    }

    /**
     * @dataProvider invalidUtf8SequencesProvider
     */
    public function testInvalidUtf8CharactersShouldNotThrow(string $invalid): void
    {
        $prefix = 'valid prefix';
        $suffix = 'valid suffix';
        $exception = new \RuntimeException($prefix . $invalid . $suffix);

        $factory = new ErrorResponseFactory();
        $response = $factory->getResponseFromException($exception);
        $json = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $json);
        static::assertArrayHasKey(0, $json['errors']);
        static::assertArrayHasKey('detail', $json['errors'][0]);

        static::assertStringStartsWith($prefix, $json['errors'][0]['detail']);
        static::assertStringEndsWith($suffix, $json['errors'][0]['detail']);
    }

    public function testResourceValueShouldNotThrow(): void
    {
        $fileResource = false;

        try {
            $closedFileResource = \tmpfile();
            static::assertTrue(\is_resource($closedFileResource));
            fclose($closedFileResource);

            $fileResource = \tmpfile();
            static::assertTrue(\is_resource($fileResource));

            $exception = new SimpleShopwareHttpException([
                'normal' => 'value',
                'resource' => $fileResource,
                'closed_resource' => $closedFileResource,
            ]);

            $factory = new ErrorResponseFactory();
            // might throw a InvalidArgumentException: Type is not supported
            // because a resource was passed to json_encode
            $response = $factory->getResponseFromException($exception);
            static::assertSame(Response::HTTP_I_AM_A_TEAPOT, $response->getStatusCode());
        } finally {
            if ($fileResource) {
                fclose($fileResource);
            }
        }
    }
}

/**
 * @internal
 */
class SimpleShopwareHttpException extends ShopwareHttpException
{
    final public const EXCEPTION_CODE = 'FRAMEWORK__TEST_EXCEPTION';
    final public const EXCEPTION_MESSAGE = 'this is param 1: {{ paramOne }} and this is param 2: {{ paramTwo }}';

    /**
     * @param array<string, mixed> $params
     */
    public function __construct(array $params)
    {
        parent::__construct(self::EXCEPTION_MESSAGE, $params);
    }

    public function getErrorCode(): string
    {
        return self::EXCEPTION_CODE;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_I_AM_A_TEAPOT;
    }
}
