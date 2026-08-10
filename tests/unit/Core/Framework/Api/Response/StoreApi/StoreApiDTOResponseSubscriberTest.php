<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Response\StoreApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Response\StoreApi\StoreApiDTOResponseInterface;
use Shopware\Core\Framework\Api\Response\StoreApi\StoreApiDTOResponseSubscriber;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiDTOResponseSubscriber::class)]
class StoreApiDTOResponseSubscriberTest extends TestCase
{
    public function testSubscribesToViewEvent(): void
    {
        static::assertSame(
            [KernelEvents::VIEW => ['onView', 1000]],
            StoreApiDTOResponseSubscriber::getSubscribedEvents(),
        );
    }

    public function testConvertsResponseDtoToJsonResponse(): void
    {
        $event = $this->createViewEvent(new class implements StoreApiDTOResponseInterface {
            public string $status = 'optIn';

            public string $apiAlias = 'account_newsletter_recipient';
        });

        (new StoreApiDTOResponseSubscriber())->onView($event);

        static::assertInstanceOf(JsonResponse::class, $event->getResponse());
        static::assertSame(
            '{"status":"optIn","apiAlias":"account_newsletter_recipient"}',
            $event->getResponse()->getContent(),
        );
    }

    public function testLeavesNonResponseResultUntouched(): void
    {
        $event = $this->createViewEvent(new \stdClass());

        (new StoreApiDTOResponseSubscriber())->onView($event);

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
