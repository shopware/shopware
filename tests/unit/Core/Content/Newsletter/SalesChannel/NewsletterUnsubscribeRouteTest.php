<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Content\Newsletter\NewsletterException;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterUnsubscribeRoute::class)]
class NewsletterUnsubscribeRouteTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = Generator::generateSalesChannelContext();
    }

    public function testUnsubscribe(): void
    {
        $requestData = new RequestDataBag();
        $requestData->add([
            'email' => 'test@example.com',
        ]);

        $newsletterRecipientEntity = new NewsletterRecipientEntity();
        $newsletterRecipientEntity->setId(Uuid::randomHex());
        $newsletterRecipientEntity->setEmail('test@example.com');
        $newsletterRecipientEntity->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $newsletterRecipientEntity->setConfirmedAt(new \DateTime());

        /** @var StaticEntityRepository<NewsletterRecipientCollection> $entityRepository */
        $entityRepository = new StaticEntityRepository([
            new NewsletterRecipientCollection([$newsletterRecipientEntity]),
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                static::isInstanceOf(NewsletterUnsubscribeEvent::class),
            );

        $newsletterSubscribeRoute = new NewsletterUnsubscribeRoute(
            $entityRepository,
            $this->createMock(DataValidator::class),
            $eventDispatcher,
            $this->createMock(RateLimiter::class),
            $this->createMock(RequestStack::class),
        );

        $response = $newsletterSubscribeRoute->unsubscribeWithResponse($requestData, $this->salesChannelContext);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame([
            [
                [
                    'email' => $newsletterRecipientEntity->getEmail(),
                    'id' => $newsletterRecipientEntity->getId(),
                    'status' => NewsletterSubscribeRoute::STATUS_OPT_OUT,
                ],
            ],
        ], $entityRepository->updates);
    }

    public function testUnsubscribeWithoutEmail(): void
    {
        $requestData = new RequestDataBag();
        $requestData->add([
            'email' => null,
        ]);

        /** @var StaticEntityRepository<NewsletterRecipientCollection> $entityRepository */
        $entityRepository = new StaticEntityRepository([]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->never())
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                static::isInstanceOf(NewsletterUnsubscribeEvent::class),
            );

        $newsletterSubscribeRoute = new NewsletterUnsubscribeRoute(
            $entityRepository,
            $this->createMock(DataValidator::class),
            $eventDispatcher,
            $this->createMock(RateLimiter::class),
            $this->createMock(RequestStack::class),
        );

        $this->expectExceptionObject(NewsletterException::missingEmailParameter());
        $response = $newsletterSubscribeRoute->unsubscribeWithResponse($requestData, $this->salesChannelContext);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testUnsubscribeWithNotFoundEmail(): void
    {
        $requestData = new RequestDataBag();
        $requestData->add([
            'email' => 'test@example.com',
        ]);

        /** @var StaticEntityRepository<NewsletterRecipientCollection> $entityRepository */
        $entityRepository = new StaticEntityRepository([
            new NewsletterRecipientCollection([]),
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->never())
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                static::isInstanceOf(NewsletterUnsubscribeEvent::class),
            );

        $newsletterSubscribeRoute = new NewsletterUnsubscribeRoute(
            $entityRepository,
            $this->createMock(DataValidator::class),
            $eventDispatcher,
            $this->createMock(RateLimiter::class),
            $this->createMock(RequestStack::class),
        );

        $this->expectExceptionObject(NewsletterException::recipientNotFound('email', 'test@example.com'));
        $response = $newsletterSubscribeRoute->unsubscribeWithResponse($requestData, $this->salesChannelContext);
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testUnsubscribeRateLimiterIsCalled(): void
    {
        $requestData = new RequestDataBag();
        $requestData->add([
            'email' => 'test@example.com',
        ]);

        $newsletterRecipientEntity = new NewsletterRecipientEntity();
        $newsletterRecipientEntity->setId(Uuid::randomHex());
        $newsletterRecipientEntity->setEmail('test@example.com');
        $newsletterRecipientEntity->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $newsletterRecipientEntity->setConfirmedAt(new \DateTime());

        /** @var StaticEntityRepository<NewsletterRecipientCollection> $entityRepository */
        $entityRepository = new StaticEntityRepository([
            new NewsletterRecipientCollection([$newsletterRecipientEntity]),
        ]);

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted');

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->once())->method('getMainRequest')->willReturn($request);

        $newsletterSubscribeRoute = new NewsletterUnsubscribeRoute(
            $entityRepository,
            $this->createMock(DataValidator::class),
            $this->createMock(EventDispatcherInterface::class),
            $rateLimiter,
            $requestStack,
        );

        $response = $newsletterSubscribeRoute->unsubscribeWithResponse($requestData, $this->salesChannelContext);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
