<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Api\Response\DTOResponseListener;
use Shopware\Core\Framework\Log\Package;
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
    public function testConvertsResponseDtoToJsonResponse(): void
    {
        $event = $this->createViewEvent(new #[JsonStreamable] class extends AbstractResponse {
            public string $status = 'optIn';

            public string $apiAlias = 'account_newsletter_recipient';
        });

        $this->listener()($event);

        static::assertInstanceOf(JsonResponse::class, $event->getResponse());
        static::assertSame(
            '{"status":"optIn","apiAlias":"account_newsletter_recipient"}',
            $event->getResponse()->getContent(),
        );
    }

    public function testLeavesNonResponseResultUntouched(): void
    {
        $event = $this->createViewEvent(new \stdClass());

        $this->listener()($event);

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
            }

            /**
             * @var list<object>
             */
            public array $relatedAddresses = [];
        };
        $response->relatedAddresses = [$nestedAddress];

        $event = $this->createViewEvent($response);

        $this->listener()($event);

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

        $this->listener()($event);

        static::assertSame('{"extensions":{"customData":{"value":"test"}}}', $event->getResponse()?->getContent());
    }

    public function testOmitsNullNullableResponseProperty(): void
    {
        $response = new #[JsonStreamable] class extends AbstractResponse {
            public ?string $message = null;
        };

        $event = $this->createViewEvent($response);

        $this->listener()($event);

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

        $this->listener()($event);

        static::assertSame(Response::HTTP_CREATED, $event->getResponse()?->getStatusCode());
        static::assertSame('application/json', $event->getResponse()->headers->get('Content-Type'));
        static::assertSame('value', $event->getResponse()->headers->get('X-Test'));
        static::assertNotEmpty($event->getResponse()->headers->getCookies());
        static::assertSame('max-age=60, public', $event->getResponse()->headers->get('Cache-Control'));
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

    private function listener(): DTOResponseListener
    {
        return new DTOResponseListener(JsonStreamWriter::create());
    }
}
