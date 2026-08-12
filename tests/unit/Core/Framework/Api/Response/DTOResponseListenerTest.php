<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Api\Response\DTOResponseListener;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DTOResponseListener::class)]
class DTOResponseListenerTest extends TestCase
{
    public function testConvertsResponseDtoToJsonResponse(): void
    {
        $event = $this->createViewEvent(new class extends AbstractResponse {
            public string $status = 'optIn';

            public string $apiAlias = 'account_newsletter_recipient';
        });

        (new DTOResponseListener())($event);

        static::assertInstanceOf(JsonResponse::class, $event->getResponse());
        static::assertSame(
            '{"status":"optIn","apiAlias":"account_newsletter_recipient"}',
            $event->getResponse()->getContent(),
        );
    }

    public function testLeavesNonResponseResultUntouched(): void
    {
        $event = $this->createViewEvent(new \stdClass());

        (new DTOResponseListener())($event);

        static::assertNull($event->getResponse());
    }

    public function testConvertsResponseDtoWithNestedObjectsToJsonResponse(): void
    {
        $nestedAddress = new class {
            public string $city = 'Berlin';
        };
        $response = new class($nestedAddress) extends AbstractResponse {
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

        (new DTOResponseListener())($event);

        static::assertSame(
            '{"address":{"city":"Berlin"},"relatedAddresses":[{"city":"Berlin"}]}',
            $event->getResponse()?->getContent(),
        );
    }

    public function testConvertsResponseDtoExtensionsToJsonResponse(): void
    {
        $response = new class extends AbstractResponse {
        };
        $response->addExtension('customData', ['value' => 'test']);

        $event = $this->createViewEvent($response);

        (new DTOResponseListener())($event);

        static::assertSame('{"extensions":{"customData":{"value":"test"}}}', $event->getResponse()?->getContent());
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
}
