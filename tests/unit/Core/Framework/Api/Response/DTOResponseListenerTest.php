<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Api\Response\DTOResponseListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\JsonStreamer\JsonStreamWriter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DTOResponseListener::class)]
class DTOResponseListenerTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function testConvertsResponseDtoToJsonResponse(): void
    {
        $event = $this->createViewEvent(new #[JsonStreamable] class extends AbstractResponse {
            public string $status = 'optIn';

            public string $apiAlias = 'account_newsletter_recipient';
        });

        $this->createListener()($event);

        static::assertInstanceOf(JsonResponse::class, $event->getResponse());
        static::assertSame(
            '{"status":"optIn","apiAlias":"account_newsletter_recipient"}',
            $event->getResponse()->getContent(),
        );
    }

    public function testLeavesNonResponseResultUntouched(): void
    {
        $event = $this->createViewEvent(new \stdClass());
        $event->getRequest()->attributes->set('_route', 'store-api.test');
        $this->dispatcher->addListener('store-api.test.encode', static function (): void {
            static::fail('Must not dispatch an encode event for a non-DTO result.');
        });
        $media = $this->createMock(MediaUrlPlaceholderHandlerInterface::class);
        $media->expects($this->never())->method('replace');
        $seo = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seo->expects($this->never())->method('replace');

        $this->createListener($media, $seo)($event);

        static::assertNull($event->getResponse());
    }

    public function testConvertsResponseDtoWithNestedObjectsToJsonResponse(): void
    {
        $nestedAddress = new #[JsonStreamable] class {
            public string $city = 'Berlin';
        };
        $response = new #[JsonStreamable] class($nestedAddress) extends AbstractResponse {
            public function __construct(public object $address)
            {
                parent::__construct();
            }

            /**
             * @var list<object>
             */
            public array $relatedAddresses = [];
        };
        $response->relatedAddresses = [$nestedAddress];

        $event = $this->createViewEvent($response);

        $this->createListener()($event);

        static::assertSame(
            '{"address":{"city":"Berlin"},"relatedAddresses":[{"city":"Berlin"}]}',
            $event->getResponse()?->getContent(),
        );
    }

    public function testConvertsResponseDtoExtensionsToJsonResponse(): void
    {
        $response = new #[JsonStreamable] class extends AbstractResponse {
        };
        $response->addExtension('customData', ['value' => 'test']);

        $event = $this->createViewEvent($response);

        $this->createListener()($event);

        static::assertSame('{"extensions":{"customData":{"value":"test"}}}', $event->getResponse()?->getContent());
    }

    public function testOmitsNullNullableResponseProperty(): void
    {
        $response = new #[JsonStreamable] class extends AbstractResponse {
            public ?string $message = null;
        };

        $event = $this->createViewEvent($response);

        $this->createListener()($event);

        static::assertSame('{}', $event->getResponse()?->getContent());
    }

    public function testPreservesSchemaStatusAndResponseMetadata(): void
    {
        $response = new #[JsonStreamable] class extends AbstractResponse {
            public function __construct()
            {
                parent::__construct(statusCode: Response::HTTP_CREATED);
            }

            public string $id = 'test';
        };
        $response->setHeader('X-Test', 'value');
        $response->addCookie(new Cookie('test', 'value'));
        $response->setHeader('Cache-Control', 'max-age=60, public');
        $event = $this->createViewEvent($response);

        $this->createListener()($event);

        static::assertSame(Response::HTTP_CREATED, $event->getResponse()?->getStatusCode());
        static::assertSame('application/json', $event->getResponse()->headers->get('Content-Type'));
        static::assertSame('value', $event->getResponse()->headers->get('X-Test'));
        static::assertNotEmpty($event->getResponse()->headers->getCookies());
        static::assertSame('max-age=60, public', $event->getResponse()->headers->get('Cache-Control'));
    }

    public function testEncodeEventCanModifyDtoBeforeSerialization(): void
    {
        $dto = new #[JsonStreamable] class extends AbstractResponse {
            public string $status = 'before';
        };
        $event = $this->createViewEvent($dto);
        $event->getRequest()->attributes->set('_route', 'store-api.test');
        $this->dispatcher->addListener('store-api.test.encode', static function (ViewEvent $dispatched) use ($event, $dto): void {
            static::assertSame($event, $dispatched);
            static::assertSame($dto, $dispatched->getControllerResult());
            static::assertFalse($dispatched->hasResponse());
            $dto->status = 'after';
            $dto->setStatusCode(Response::HTTP_ACCEPTED);
        });

        $this->createListener()($event);

        static::assertSame('{"status":"after"}', $event->getResponse()?->getContent());
        static::assertSame(Response::HTTP_ACCEPTED, $event->getResponse()->getStatusCode());
    }

    public function testEncodeEventCanReplaceControllerResult(): void
    {
        $replacement = new #[JsonStreamable] class extends AbstractResponse {
            public string $status = 'replacement';
        };
        $event = $this->createViewEvent(new #[JsonStreamable] class extends AbstractResponse {});
        $event->getRequest()->attributes->set('_route', 'store-api.test');
        $this->dispatcher->addListener('store-api.test.encode', static function (ViewEvent $event) use ($replacement): void {
            $event->setControllerResult($replacement);
        });

        $this->createListener()($event);

        static::assertSame('{"status":"replacement"}', $event->getResponse()?->getContent());
    }

    public function testEncodeEventCanSetResponse(): void
    {
        $response = new Response('custom');
        $event = $this->createViewEvent(new #[JsonStreamable] class extends AbstractResponse {});
        $event->getRequest()->attributes->set('_route', 'store-api.test');
        $this->dispatcher->addListener('store-api.test.encode', static function (ViewEvent $event) use ($response): void {
            $event->setResponse($response);
        });

        $this->createListener()($event);

        static::assertSame($response, $event->getResponse());
    }

    public function testReplacesMediaBeforeSeoPlaceholders(): void
    {
        $dto = new #[JsonStreamable] class extends AbstractResponse {
            public string $url = '124c71d524604ccbad6042edce3ac799/mediaId/test#';
        };
        $context = static::createStub(SalesChannelContext::class);
        $event = $this->createViewEvent($dto);
        $event->getRequest()->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $media = $this->createMock(MediaUrlPlaceholderHandlerInterface::class);
        $media->expects($this->once())->method('replace')
            ->with('{"url":"124c71d524604ccbad6042edce3ac799/mediaId/test#"}')
            ->willReturn('{"url":"media-replaced"}');
        $seo = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seo->expects($this->once())->method('replace')
            ->with('{"url":"media-replaced"}', '', $context)
            ->willReturn('{"url":"seo-replaced"}');

        $this->createListener($media, $seo)($event);

        static::assertSame('{"url":"seo-replaced"}', $event->getResponse()?->getContent());
    }

    public function testReplacesMediaWithoutSalesChannelContext(): void
    {
        $event = $this->createViewEvent(new #[JsonStreamable] class extends AbstractResponse {});
        $media = $this->createMock(MediaUrlPlaceholderHandlerInterface::class);
        $media->expects($this->once())->method('replace')->with('{}')->willReturn('{"media":"replaced"}');
        $seo = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seo->expects($this->never())->method('replace');

        $this->createListener($media, $seo)($event);

        static::assertSame('{"media":"replaced"}', $event->getResponse()?->getContent());
    }

    private function createViewEvent(object $result): ViewEvent
    {
        return new ViewEvent(
            static::createStub(HttpKernelInterface::class),
            Request::create('/store-api/test'),
            HttpKernelInterface::MAIN_REQUEST,
            $result,
        );
    }

    private function createListener(
        ?MediaUrlPlaceholderHandlerInterface $media = null,
        ?SeoUrlPlaceholderHandlerInterface $seo = null,
    ): DTOResponseListener {
        if ($media === null) {
            $media = static::createStub(MediaUrlPlaceholderHandlerInterface::class);
            $media->method('replace')->willReturnArgument(0);
        }
        if ($seo === null) {
            $seo = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
            $seo->method('replace')->willReturnArgument(0);
        }

        return new DTOResponseListener(JsonStreamWriter::create(), $this->dispatcher, $seo, $media);
    }
}
