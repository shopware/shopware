<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Traits\OrderFixture;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
class OrderRecalculationControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use OrderFixture;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
    }

    public function testLineItemIsNotInjectableIntoAnOrderVersion(): void
    {
        $orderId = Uuid::randomHex();
        $versionId = Uuid::randomHex();
        $marker = 'injected-' . Uuid::randomHex();
        $this->createOrder($orderId, $versionId);

        // the write sink runs in system scope, so the version context must not become a way around the privilege
        $browser = $this->getBrowser(true, [], ['order:read']);
        $browser->setServerParameter('HTTP_SW_VERSION_ID', $versionId);
        $browser->jsonRequest('POST', \sprintf('/api/_action/order/%s/lineItem', $orderId), [
            'identifier' => $marker,
            'type' => 'custom',
            'quantity' => 1,
            'label' => $marker,
            'priceDefinition' => [
                'type' => 'quantity',
                'price' => -9999,
                'quantity' => 1,
                'taxRules' => [['taxRate' => 0, 'percentage' => 100]],
            ],
        ]);

        $content = (string) $browser->getResponse()->getContent();
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $content);
        static::assertSame(
            MissingPrivilegeException::MISSING_PRIVILEGE_ERROR,
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['errors'][0]['code'],
            $content
        );

        $lineItemRepository = static::getContainer()->get('order_line_item.repository');
        static::assertInstanceOf(EntityRepository::class, $lineItemRepository);

        $this->context->assign(['versionId' => $versionId]);
        $criteria = (new Criteria())->addFilter(new EqualsFilter('identifier', $marker));

        static::assertCount(0, $lineItemRepository->searchIds($criteria, $this->context)->getIds());
    }

    public function testRecalculateIsAllowedForOrderEditor(): void
    {
        $browser = $this->getBrowser(true, [], ['order:read', 'order:update']);
        $browser->jsonRequest('POST', \sprintf('/api/_action/order/%s/recalculate', Uuid::randomHex()));

        // the order does not exist, but the request must pass the privilege check to get there
        static::assertSame(
            Response::HTTP_NOT_FOUND,
            $browser->getResponse()->getStatusCode(),
            (string) $browser->getResponse()->getContent()
        );
    }

    private function createOrder(string $orderId, string $versionId): void
    {
        $orderData = $this->getOrderData($orderId, $this->context)[0];

        $orderData['versionId'] = $versionId;
        $orderData['orderCustomer']['orderVersionId'] = $versionId;

        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        $orderRepository->create([$orderData], $this->context);
    }
}
