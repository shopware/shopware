<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(StoreApiException::class)]
class StoreApiExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        $clientException = new ClientException(
            'message',
            new Request('GET', 'https://example.com'),
            new \GuzzleHttp\Psr7\Response()
        );

        static::assertSame(
            'FRAMEWORK__STORE_ERROR',
            (new StoreApiException($clientException))->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        $clientException = new ClientException(
            'message',
            new Request('GET', 'https://example.com'),
            new \GuzzleHttp\Psr7\Response()
        );

        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            (new StoreApiException($clientException))->getStatusCode()
        );
    }

    public function testGetDefaultMessage(): void
    {
        $clientException = new ClientException(
            'message',
            new Request('GET', 'https://example.com'),
            new \GuzzleHttp\Psr7\Response()
        );

        static::assertSame(
            'message',
            (new StoreApiException($clientException))->getMessage()
        );
    }

    public function testDescriptionFromResponseOverridesExceptionMessage(): void
    {
        $clientException = new ClientException(
            'message',
            new Request('GET', 'https://example.com'),
            new \GuzzleHttp\Psr7\Response(
                Response::HTTP_BAD_REQUEST,
                [],
                json_encode(['description' => 'description'], \JSON_THROW_ON_ERROR)
            )
        );

        static::assertSame(
            'description',
            (new StoreApiException($clientException))->getMessage()
        );
    }

    public function testExtractsTitleFromResponse(): void
    {
        $clientException = new ClientException(
            'message',
            new Request('GET', 'https://example.com'),
            new \GuzzleHttp\Psr7\Response(
                Response::HTTP_BAD_REQUEST,
                [],
                json_encode(['title' => 'title'], \JSON_THROW_ON_ERROR)
            )
        );

        $exception = new StoreApiException($clientException);

        foreach ($exception->getErrors() as $error) {
            static::assertSame('title', $error['title']);
        }
    }

    public function testExtractsDocumentationLinkFromResponse(): void
    {
        $clientException = new ClientException(
            'message',
            new Request('GET', 'https://example.com'),
            new \GuzzleHttp\Psr7\Response(
                Response::HTTP_BAD_REQUEST,
                [],
                json_encode(['documentationLink' => 'https://shopware.docs'], \JSON_THROW_ON_ERROR)
            )
        );

        $exception = new StoreApiException($clientException);

        foreach ($exception->getErrors() as $error) {
            static::assertSame('https://shopware.docs', $error['meta']['documentationLink']);
        }
    }

    public function testGetErrorsWithTrace(): void
    {
        $clientException = new ClientException(
            'message',
            new Request('GET', 'https://example.com'),
            new \GuzzleHttp\Psr7\Response(
                Response::HTTP_BAD_REQUEST,
                [],
                json_encode([
                    'title' => 'title',
                    'description' => 'description',
                    'documentationLink' => 'https://shopware.docs',
                ], \JSON_THROW_ON_ERROR),
            )
        );

        $exception = new StoreApiException($clientException);

        foreach ($exception->getErrors(true) as $error) {
            static::assertSame('FRAMEWORK__STORE_ERROR', $error['code']);
            static::assertSame((string) Response::HTTP_INTERNAL_SERVER_ERROR, $error['status']);
            static::assertSame('title', $error['title']);
            static::assertSame('description', $error['detail']);
            static::assertSame('https://shopware.docs', $error['meta']['documentationLink']);
            static::assertIsString($error['trace']);
        }
    }
}
