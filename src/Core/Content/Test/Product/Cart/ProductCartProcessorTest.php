<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

class ProductCartProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new TestDataCollection(Context::createDefaultContext());
    }

    public function testDeliveryInformation(): void
    {
        $this->createProduct();

        $service = $this->getContainer()->get(CartService::class);

        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($this->ids->get('product'));

        $token = $this->ids->create('token');

        $context = $this->getContainer()->get(SalesChannelContextService::class)
            ->get(Defaults::SALES_CHANNEL, $token);

        $cart = $service->getCart($token, $context);
        $service->add($cart, $product, $context);

        $product = $cart->get($product->getId());

        static::assertInstanceOf(DeliveryInformation::class, $product->getDeliveryInformation());

        $info = $product->getDeliveryInformation();
        static::assertEquals(100, $info->getWeight());
        static::assertEquals(101, $info->getHeight());
        static::assertEquals(102, $info->getWidth());
        static::assertEquals(103, $info->getLength());
    }

    private function createProduct(): void
    {
        $data = [
            'id' => $this->ids->create('product'),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'active' => true,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'weight' => 100,
            'height' => 101,
            'width' => 102,
            'length' => 103,
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
