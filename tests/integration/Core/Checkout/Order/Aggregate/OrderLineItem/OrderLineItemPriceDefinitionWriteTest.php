<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Order\Aggregate\OrderLineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
class OrderLineItemPriceDefinitionWriteTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use OrderFixture;

    /**
     * The definition used to be persisted before the API answered with a 500, after which the line item and every
     * order loading it could no longer be read.
     */
    public function testQuantityPriceDefinitionWithoutTaxRulesIsRejectedAndNotPersisted(): void
    {
        $context = Context::createDefaultContext();
        $orderData = $this->getOrderData(Uuid::randomHex(), $context);
        static::getContainer()->get('order.repository')->create($orderData, $context);
        $lineItemId = $orderData[0]['lineItems'][0]['id'];

        $browser = $this->getBrowser();
        $browser->jsonRequest('PATCH', '/api/order-line-item/' . $lineItemId, [
            'priceDefinition' => ['type' => QuantityPriceDefinition::TYPE, 'price' => 99, 'quantity' => 1],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $errors = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'];
        static::assertSame('/0/taxRules', $errors[0]['source']['pointer']);

        /** @var EntityRepository<OrderLineItemCollection> $lineItemRepository */
        $lineItemRepository = static::getContainer()->get('order_line_item.repository');
        $priceDefinition = $lineItemRepository->search(new Criteria([$lineItemId]), $context)
            ->getEntities()
            ->first()
            ?->getPriceDefinition();

        static::assertInstanceOf(QuantityPriceDefinition::class, $priceDefinition);
        static::assertSame(10.0, $priceDefinition->getPrice());
    }
}
