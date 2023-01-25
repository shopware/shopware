<?php declare(strict_types=1);

namespace Shopware\Tests\Bench\Cases\Storefront;

use Doctrine\DBAL\Connection;
use PhpBench\Attributes as Bench;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Tests\Bench\BenchCase;
use Shopware\Tests\Bench\Fixtures;

/**
 * @internal - only for performance benchmarks
 */
class CartOrderRouteBench extends BenchCase
{
    private const SUBJECT_CUSTOMER = 'customer-0';
    private const CART_ITEMS_COUNT = 10;

    private Cart $cart;

    public function setupWithLogin(): void
    {
        $this->ids = clone Fixtures::getIds();
        $this->context = Fixtures::context([
            SalesChannelContextService::CUSTOMER_ID => $this->ids->get(self::SUBJECT_CUSTOMER),
        ]);
        if (!$this->context->getCustomerId()) {
            throw new \Exception('Customer not logged in for bench tests which require it!');
        }

        $this->getContainer()->get(Connection::class)->beginTransaction();

        $baseProduct = [
            'name' => 'Test product',
            'stock' => 10,
            'manufacturerId' => $this->ids->get('manufacturer'),
            'price' => [
                ['currencyId' => $this->ids->get('currency'), 'gross' => '99.99', 'net' => '84.03', 'linked' => true],
            ],
            'taxId' => $this->ids->get('tax'),
            'categories' => [['id' => $this->ids->get('navigation')]],
            'visibilities' => [
                [
                    'salesChannelId' => $this->ids->get('sales-channel'),
                    'visibility' => 30,
                ],
            ],
        ];

        $productPayload = [];

        for ($i = 0; $i < self::CART_ITEMS_COUNT; ++$i) {
            $productPayload[] = array_merge($baseProduct, [
                'id' => $this->ids->get('product-state-physical-' . $i),
                'productNumber' => $this->ids->get('product-state-physical-' . $i),
            ]);
            $productPayload[] = array_merge($baseProduct, [
                'id' => $this->ids->get('product-state-digital-' . $i),
                'productNumber' => $this->ids->get('product-state-digital-' . $i),
                'downloads' => [
                    ['media' => ['fileName' => 'foo' . $i . '_1', 'fileExtension' => 'pdf', 'private' => true]],
                    ['media' => ['fileName' => 'foo' . $i . '_2', 'fileExtension' => 'pdf', 'private' => true]],
                    ['media' => ['fileName' => 'foo' . $i . '_3', 'fileExtension' => 'pdf', 'private' => true]],
                ],
            ]);
        }

        $this->context->getContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($productPayload): void {
            $this->getContainer()->get('product.repository')->create($productPayload, $context);
        });

        $this->cart = new Cart($this->context->getToken());

        foreach ($this->ids->prefixed('product-state-') as $id) {
            $this->cart->getLineItems()->add(new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, $id));
        }

        $this->getContainer()->get(ProductCartProcessor::class)->collect($this->cart->getData(), $this->cart, $this->context, new CartBehavior());
    }

    #[Bench\BeforeMethods(['setupWithLogin'])]
    #[Bench\Assert('mode(variant.time.avg) < 150ms +/- 20ms')]
    public function bench_order_10_physical_products(): void
    {
        $this->cart->setLineItems($this->cart->getLineItems()->filter(fn (LineItem $lineItem): bool => \in_array($lineItem->getReferencedId(), $this->ids->prefixed('product-state-physical-'), true)));
        $this->getContainer()->get(CartOrderRoute::class)->order($this->cart, $this->context, new RequestDataBag());
    }

    #[Bench\BeforeMethods(['setupWithLogin'])]
    #[Bench\Assert('mode(variant.time.avg) < 170ms +/- 20ms')]
    public function bench_order_10_digital_products(): void
    {
        $this->cart->setLineItems($this->cart->getLineItems()->filter(fn (LineItem $lineItem): bool => \in_array($lineItem->getReferencedId(), $this->ids->prefixed('product-state-digital-'), true)));
        $this->getContainer()->get(CartOrderRoute::class)->order($this->cart, $this->context, new RequestDataBag());
    }
}
