<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer;
use Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexingMessage;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerFlowEventsSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomerFlowEventsSubscriber::class)]
class CustomerFlowEventsSubscriberTest extends TestCase
{
    private MockObject&EventDispatcherInterface $dispatcher;

    private MockObject&SalesChannelContextRestorer $restorer;

    private MockObject&CustomerIndexer $customerIndexer;

    private TestDataCollection $ids;

    private CustomerFlowEventsSubscriber $customerFlowEventsSubscriber;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->restorer = $this->createMock(SalesChannelContextRestorer::class);
        $this->customerIndexer = $this->createMock(CustomerIndexer::class);

        $this->customerFlowEventsSubscriber = new CustomerFlowEventsSubscriber($this->dispatcher, $this->restorer, $this->customerIndexer);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ], $this->customerFlowEventsSubscriber->getSubscribedEvents());
    }

    public function testOnCustomerWrittenWithInstanceOfSaleChannelApi(): void
    {
        $context = Context::createDefaultContext(new SalesChannelApiSource(Defaults::SALES_CHANNEL_TYPE_API));

        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects(static::once())
            ->method('getContext')
            ->willReturn($context);

        $this->customerFlowEventsSubscriber->onCustomerWritten($event);
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed
     */
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testOnCustomerUpdateWithoutCustomerInContext(): void
    {
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects(static::exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $payloads = [
            [
                'defaultPaymentMethodId' => $this->ids->get('defaultPaymentMethod'),
                'id' => $this->ids->get('newPaymentMethod'),
            ],
        ];

        $event->expects(static::once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $this->dispatcher->expects(static::never())->method('dispatch');

        $this->customerFlowEventsSubscriber->onCustomerWritten($event);
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed
     */
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testOnCustomerUpdateWithCustomer(): void
    {
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects(static::exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $payloads = [
            [
                'defaultPaymentMethodId' => $this->ids->get('defaultPaymentMethod'),
                'id' => $this->ids->get('newPaymentMethod'),
            ],
        ];

        $event->expects(static::once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects(static::once())
            ->method('getCustomer')
            ->willReturn($customer);

        $this->restorer->expects(static::once())
            ->method('restoreByCustomer')
            ->willReturn($salesChannelContext);

        $customerChangePaymentMethodEvent = new CustomerChangedPaymentMethodEvent(
            $salesChannelContext,
            $customer,
            new RequestDataBag()
        );

        $this->dispatcher->expects(static::once())
            ->method('dispatch')
            ->with($customerChangePaymentMethodEvent);

        $this->customerFlowEventsSubscriber->onCustomerWritten($event);
    }

    public function testOnCustomerCreatedWithoutCustomerInContext(): void
    {
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects(static::exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $payloads = [
            [
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->get('newPaymentMethod'),
            ],
        ];

        $event->expects(static::once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $this->dispatcher->expects(static::never())->method('dispatch');

        $this->customerFlowEventsSubscriber->onCustomerWritten($event);
    }

    public function testOnCustomerCreatedWithCustomer(): void
    {
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects(static::exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $payloads = [
            [
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->get('customerId'),
            ],
        ];

        $event->expects(static::once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $this->customerIndexer->expects(static::once())
            ->method('handle')
            ->with(new CustomerIndexingMessage([$this->ids->get('customerId')]));

        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->expects(static::once())
            ->method('getCustomer')
            ->willReturn($customer);

        $this->restorer->expects(static::once())
            ->method('restoreByCustomer')
            ->willReturn($salesChannelContext);

        $customerCreated = new CustomerRegisterEvent(
            $salesChannelContext,
            $customer
        );

        $this->dispatcher->expects(static::once())
            ->method('dispatch')
            ->with($customerCreated);

        $this->customerFlowEventsSubscriber->onCustomerWritten($event);
    }
}
