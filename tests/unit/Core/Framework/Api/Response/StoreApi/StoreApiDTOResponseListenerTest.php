<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Response\StoreApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Response\StoreApi\StoreApiDTOResponseInterface;
use Shopware\Core\Framework\Api\Response\StoreApi\StoreApiDTOResponseListener;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiDTOResponseListener::class)]
class StoreApiDTOResponseListenerTest extends TestCase
{
    public function testConvertsResponseDtoToJsonResponse(): void
    {
        $event = $this->createViewEvent(new class implements StoreApiDTOResponseInterface {
            public string $status = 'optIn';

            public string $apiAlias = 'account_newsletter_recipient';
        });

        (new StoreApiDTOResponseListener())($event);

        static::assertInstanceOf(JsonResponse::class, $event->getResponse());
        static::assertSame(
            '{"status":"optIn","apiAlias":"account_newsletter_recipient"}',
            $event->getResponse()->getContent(),
        );
    }

    public function testLeavesNonResponseResultUntouched(): void
    {
        $event = $this->createViewEvent(new \stdClass());

        (new StoreApiDTOResponseListener())($event);

        static::assertNull($event->getResponse());
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
