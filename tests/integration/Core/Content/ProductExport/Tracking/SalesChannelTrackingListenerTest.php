<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ProductExport\Tracking;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerCollection;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerEntity;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingListener;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderCollection;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelTrackingListenerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    /**
     * @var EntityRepository<SalesChannelTrackingOrderCollection>
     */
    private EntityRepository $trackingOrderRepository;

    /**
     * @var EntityRepository<SalesChannelTrackingCustomerCollection>
     */
    private EntityRepository $trackingCustomerRepository;

    private SalesChannelTrackingListener $listener;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->customerRepository = static::getContainer()->get('customer.repository');

        $this->trackingOrderRepository = static::getContainer()->get('sales_channel_tracking_order.repository');

        $this->trackingCustomerRepository = static::getContainer()->get('sales_channel_tracking_customer.repository');

        $listener = static::getContainer()->get(SalesChannelTrackingListener::class);
        static::assertInstanceOf(SalesChannelTrackingListener::class, $listener);
        $this->listener = $listener;
    }

    public function testOrderTrackingRecordIsWrittenOnOrderInsert(): void
    {
        $agenticChannelId = $this->createAgenticCommerceChannel();

        $this->pushRequestWithReferralCode($agenticChannelId);

        $ids = new IdsCollection();
        $order = (new OrderBuilder($ids, 'order-1'))
            ->add('salesChannelId', TestDefaults::SALES_CHANNEL)
            ->build();

        $this->orderRepository->create([$order], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $ids->get('order-1')));

        $result = $this->trackingOrderRepository->search($criteria, $this->context);

        static::assertCount(1, $result);

        $tracking = $result->first();
        static::assertInstanceOf(SalesChannelTrackingOrderEntity::class, $tracking);
        static::assertSame($agenticChannelId, $tracking->getSalesChannelId());
    }

    public function testCustomerTrackingRecordIsWrittenOnCustomerInsert(): void
    {
        $agenticChannelId = $this->createAgenticCommerceChannel();

        $this->pushRequestWithReferralCode($agenticChannelId);

        $ids = new IdsCollection();
        $customer = (new CustomerBuilder($ids, 'customer-1'))->build();

        $this->customerRepository->create([$customer], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $ids->get('customer-1')));

        $result = $this->trackingCustomerRepository->search($criteria, $this->context);

        static::assertCount(1, $result);

        $tracking = $result->first();
        static::assertInstanceOf(SalesChannelTrackingCustomerEntity::class, $tracking);
        static::assertSame($agenticChannelId, $tracking->getSalesChannelId());
    }

    public function testNoTrackingWrittenWithoutReferralCodeInSession(): void
    {
        $ids = new IdsCollection();
        $customer = (new CustomerBuilder($ids, 'customer-1'))->build();

        $this->customerRepository->create([$customer], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $ids->get('customer-1')));

        $result = $this->trackingCustomerRepository->search($criteria, $this->context);

        static::assertCount(0, $result);
    }

    public function testNoTrackingWrittenForNonAgenticChannel(): void
    {
        $storefrontChannelId = $this->createSalesChannel(['typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT])['id'];

        $session = $this->getSession();
        $request = new Request([SalesChannelTrackingListener::QUERY_PARAM => $storefrontChannelId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);
        $request->setSession($session);

        static::getContainer()->get(RequestStack::class)->push($request);

        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => new \stdClass(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->listener->storeReferralCode($event);

        static::assertFalse($session->has(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE));
    }

    public function testUpdateDoesNotCreateTrackingRecord(): void
    {
        $agenticChannelId = $this->createAgenticCommerceChannel();

        $ids = new IdsCollection();
        $customer = (new CustomerBuilder($ids, 'customer-1'))->build();

        $this->customerRepository->create([$customer], $this->context);

        $this->pushRequestWithReferralCode($agenticChannelId);

        $this->customerRepository->update([
            ['id' => $ids->get('customer-1'), 'firstName' => 'Updated'],
        ], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $ids->get('customer-1')));

        $result = $this->trackingCustomerRepository->search($criteria, $this->context);

        static::assertCount(0, $result);
    }

    private function createAgenticCommerceChannel(): string
    {
        $channelId = Uuid::randomHex();

        $this->createSalesChannel([
            'id' => $channelId,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE,
        ]);

        return $channelId;
    }

    private function pushRequestWithReferralCode(string $referralCode): void
    {
        $session = $this->getSession();
        $session->set(SalesChannelTrackingListener::SESSION_KEY_REFERRAL_CODE, $referralCode);

        $request = new Request();
        $request->setSession($session);

        static::getContainer()->get(RequestStack::class)->push($request);
    }
}
