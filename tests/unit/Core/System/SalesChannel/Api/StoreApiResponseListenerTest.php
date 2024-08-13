<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Api\StoreApiResponseListener;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(StoreApiResponseListener::class)]
class StoreApiResponseListenerTest extends TestCase
{
    private StructEncoder&MockObject $encoder;

    private StoreApiResponseListener $listener;

    protected function setUp(): void
    {
        $this->encoder = $this->createMock(StructEncoder::class);
        $this->listener = new StoreApiResponseListener($this->encoder);
    }

    public function testEncodeResponseWithIncludesSpecialCharacters(): void
    {
        $this->encoder->expects(static::once())
            ->method('encode')
            ->willReturn(['encoded' => 'data']);

        $responseObject = new class extends Struct {};

        $response = $this->createMock(StoreApiResponse::class);
        $response->method('getObject')
            ->willReturn($responseObject);
        $response->method('getStatusCode')
            ->willReturn(200);
        $response->headers = new ResponseHeaderBag();

        $request = new Request();
        $request->query->set('includes', 'field1!@#$%^&*(),field2');

        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->listener->encodeResponse($event);

        $response = $event->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        $content = $response->getContent();
        static::assertIsString($content, 'Response content is not a string.');
        $decoded = json_decode($content, true);
        static::assertIsArray($decoded, 'Decoded JSON is not an array.');
        static::assertEquals(['encoded' => 'data'], $decoded);
    }

    public function testEncodeResponseWithDifferentStatusCode(): void
    {
        $this->encoder->expects(static::once())
            ->method('encode')
            ->willReturn(['encoded' => 'data']);

        $responseObject = new class extends Struct {};

        $response = $this->createMock(StoreApiResponse::class);
        $response->method('getObject')
            ->willReturn($responseObject);
        $response->method('getStatusCode')
            ->willReturn(404);
        $response->headers = new ResponseHeaderBag();

        $request = new Request();
        $request->query->set('includes', []);

        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->listener->encodeResponse($event);

        $response = $event->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(404, $response->getStatusCode());
        $content = $response->getContent();
        static::assertIsString($content, 'Response content is not a string.');
        $decoded = json_decode($content, true);
        static::assertIsArray($decoded, 'Decoded JSON is not an array.');
        static::assertEquals(['encoded' => 'data'], $decoded);
    }

    public function testEncodeResponsePreservesHeaders(): void
    {
        $this->encoder->expects(static::once())
            ->method('encode')
            ->willReturn(['encoded' => 'data']);

        $responseObject = new class extends Struct {};

        $response = $this->createMock(StoreApiResponse::class);
        $response->method('getObject')
            ->willReturn($responseObject);
        $response->method('getStatusCode')
            ->willReturn(200);
        $response->headers = new ResponseHeaderBag();
        $response->headers->set('X-Custom-Header', 'value');

        $request = new Request();
        $request->query->set('includes', []);

        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->listener->encodeResponse($event);

        $response = $event->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame('value', $response->headers->get('X-Custom-Header'));
        $content = $response->getContent();
        static::assertIsString($content, 'Response content is not a string.');
        $decoded = json_decode($content, true);
        static::assertIsArray($decoded, 'Decoded JSON is not an array.');
        static::assertEquals(['encoded' => 'data'], $decoded);
    }
}
