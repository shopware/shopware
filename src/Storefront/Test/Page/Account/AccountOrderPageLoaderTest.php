<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class AccountOrderPageLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    /**
     * @var TestDataCollection
     */
    private $ids;

    private $salesChannel;

    private EntityRepositoryInterface $customerRepository;

    private EntityRepositoryInterface $orderRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->salesChannel = $this->createSalesChannelContext();
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
    }

    public function testLogsInGuestById(): void
    {
        $context = Context::createDefaultContext();
        $unexpectedCustomer = $this->createCustomer();
        $expectedCustomer = $this->createCustomer();

        $unexpectedCustomer->setEmail('identical@shopware.com');
        $expectedCustomer->setEmail('identical@shopware.com');
        $expectedCustomer->setGuest(true);

        $this->customerRepository->update([
            [
                'id' => $unexpectedCustomer->getId(),
                'email' => $unexpectedCustomer->getEmail(),
            ],
            [
                'id' => $expectedCustomer->getId(),
                'email' => $expectedCustomer->getEmail(),
                'guest' => $expectedCustomer->getGuest(),
            ],
        ], $context);

        $salesChannel = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $this->salesChannel->getToken(),
            $this->salesChannel->getSalesChannelId(),
            [SalesChannelContextService::CUSTOMER_ID => $expectedCustomer->getId()],
        );
        $orderId = $this->placeRandomOrder($salesChannel);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();
        $this->orderRepository->update([
            [
                'id' => $order->getId(),
                'deepLinkCode' => $deepLinkCode = Random::getBase64UrlString(32),
                'orderCustomer.customerId' => $expectedCustomer->getId(),
            ],
        ], $context);

        $page = $this->getPageLoader()->load(
            new Request(
                [
                    'deepLinkCode' => $deepLinkCode,
                    'email' => $expectedCustomer->getEmail(),
                    'zipcode' => '12345',
                ],
            ),
            $this->salesChannel
        );

        static::assertEquals(
            $expectedCustomer->getId(),
            $page->getOrders()->first()->getOrderCustomer()->getCustomerId(),
        );
    }

    protected function getPageLoader(): AccountOrderPageLoader
    {
        return $this->getContainer()->get(AccountOrderPageLoader::class);
    }
}
