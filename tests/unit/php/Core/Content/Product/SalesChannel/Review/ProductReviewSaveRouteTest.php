<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute
 */
class ProductReviewSaveRouteTest extends TestCase
{
    private MockObject&EntityRepository $repository;

    private MockObject&DataValidator $validator;

    private MockObject&SystemConfigService $config;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private ProductReviewSaveRoute $route;

    public function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->validator = $this->createMock(DataValidator::class);
        $this->config = $this->createMock(SystemConfigService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->route = new ProductReviewSaveRoute(
            $this->repository,
            $this->validator,
            $this->config,
            $this->eventDispatcher
        );
    }

    public function testSave(): void
    {
        $id = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $data = new RequestDataBag([
            'id' => $id,
            'title' => 'foo',
            'content' => 'bar',
            'points' => 3,
        ]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $context = Context::createDefaultContext();
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setFirstName('Max');
        $customer->setLastName('Mustermann');
        $customer->setEmail('foo@example.com');
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $salesChannelContext->expects(static::once())->method('getCustomer')->willReturn($customer);
        $salesChannelContext->expects(static::exactly(4))->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->expects(static::exactly(4))->method('getContext')->willReturn($context);

        $this->config
            ->expects(static::exactly(2))
            ->method('get')
            ->withConsecutive(['core.listing.showReview'], ['core.basicInformation.email'])
            ->willReturnOnConsecutiveCalls(true, 'noreply@example.com');

        $this->validator->expects(static::once())->method('getViolations')->willReturn(new ConstraintViolationList());

        $this->repository
            ->expects(static::once())
            ->method('upsert')
            ->with([
                [
                    'productId' => $productId,
                    'customerId' => $customer->getId(),
                    'salesChannelId' => $salesChannel->getId(),
                    'languageId' => $context->getLanguageId(),
                    'externalUser' => $customer->getFirstName(),
                    'externalEmail' => $customer->getEmail(),
                    'title' => $data->get('title'),
                    'content' => $data->get('content'),
                    'points' => $data->get('points'),
                    'status' => false,
                    'id' => $data->get('id'),
                ],
            ], $context);

        $event = new ReviewFormEvent(
            $context,
            $salesChannel->getId(),
            new MailRecipientStruct(['noreply@example.com' => 'noreply@example.com']),
            new RequestDataBag([
                'title' => 'foo',
                'content' => 'bar',
                'points' => 3,
                'name' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'email' => $customer->getEmail(),
                'customerId' => $customer->getId(),
                'productId' => $productId,
                'id' => $id,
            ]),
            $productId,
            $customer->getId()
        );

        $this->eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with($event, ReviewFormEvent::EVENT_NAME);

        static::assertInstanceOf(
            NoContentResponse::class,
            $this->route->save($productId, $data, $salesChannelContext)
        );
    }
}
