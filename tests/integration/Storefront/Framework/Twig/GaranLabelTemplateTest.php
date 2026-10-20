<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class GaranLabelTemplateTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Context $context;

    private string $productId;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $product = (new ProductBuilder($ids, productNumber: 'garan-product'))
            ->price(gross: 10)
            ->manufacturer(key: 'acme')
            ->build();
        $product['manufacturerNumber'] = 'ACME-123';
        $product['guaranteeMonths'] = 36;
        $product['guaranteeConfirmed'] = true;

        $this->productId = $ids->get('garan-product');
        static::getContainer()->get('product.repository')->create([$product], $this->context);
    }

    #[DataProvider('cartDisplayModes')]
    public function testCartProductRendersGaranLabel(string $displayMode): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, $this->productId);

        $html = $this->renderLineItem($lineItem, $displayMode);

        static::assertStringContainsString('line-item-garan-label', $html);
        static::assertStringContainsString('<svg', $html);
    }

    #[DataProvider('orderReferences')]
    public function testOrderProductRendersGaranLabel(?string $referencedId): void
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setProductId($this->productId);
        $lineItem->setReferencedId($referencedId ?? $this->productId);

        $html = $this->renderLineItem($lineItem, displayMode: 'order');

        static::assertStringContainsString('line-item-garan-label', $html);
        static::assertStringContainsString('<svg', $html);
    }

    public function testOrderWithoutLinkedProductOmitsGaranLabel(): void
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setProductId(null);
        $lineItem->setReferencedId('external-product-reference');

        $html = $this->renderLineItem($lineItem, displayMode: 'order');

        static::assertSame('', trim($html));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function cartDisplayModes(): \Generator
    {
        yield 'cart and checkout' => ['default'];
        yield 'offcanvas cart' => ['offcanvas'];
    }

    /**
     * @return \Generator<string, array{?string}>
     */
    public static function orderReferences(): \Generator
    {
        yield 'reference matches linked product' => [null];
        yield 'external reference does not identify a product' => ['external-product-reference'];
    }

    private function renderLineItem(LineItem|OrderLineItemEntity $lineItem, string $displayMode): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig->render('@Storefront/storefront/component/line-item/element/garan-label.html.twig', [
            'context' => ['context' => $this->context],
            'lineItem' => $lineItem,
            'displayMode' => $displayMode,
        ]);
    }
}
