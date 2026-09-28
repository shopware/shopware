<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page\Product;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
class DigitalProductQuantitySelectTemplateTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('buyWidgetCases')]
    public function testBuyWidgetShowsQuantitySelectUnlessDigitalProductIsLimitedToOneUnit(string $productType, int $calculatedMaxPurchase, bool $expectsQuantitySelect): void
    {
        $product = new SalesChannelProductEntity();
        $product->assign([
            'id' => Uuid::randomHex(),
            'type' => $productType,
            'available' => true,
            'childCount' => 0,
            'isCloseout' => false,
            'minPurchase' => 1,
            'purchaseSteps' => 1,
            'calculatedMaxPurchase' => $calculatedMaxPurchase,
            'translated' => ['name' => 'Product'],
        ]);

        $html = $this->render('@Storefront/storefront/component/buy-widget/buy-widget-form.html.twig', [
            'product' => $product,
        ]);

        static::assertSame($expectsQuantitySelect, (new Crawler($html))->filter('.product-detail-quantity-group')->count() > 0);
    }

    public static function buyWidgetCases(): \Generator
    {
        yield 'digital product limited to one unit hides the quantity select' => [ProductDefinition::TYPE_DIGITAL, 1, false];
        yield 'digital product with a max purchase above 1 shows the quantity select' => [ProductDefinition::TYPE_DIGITAL, 5, true];
        yield 'physical product limited to one unit keeps the quantity select' => [ProductDefinition::TYPE_PHYSICAL, 1, true];
    }

    #[DataProvider('lineItemCases')]
    public function testLineItemQuantitySelectIsDisabledUntilMaxPurchaseAllowsMoreThanOneUnit(string $productType, int $maxPurchase, bool $expectsDisabled): void
    {
        $lineItem = (new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex()))
            ->setStackable(true)
            ->setQuantityInformation(
                (new QuantityInformation())
                    ->setMinPurchase(1)
                    ->setPurchaseSteps(1)
                    ->setMaxPurchase($maxPurchase)
            )
            ->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, $productType);

        $html = $this->render('@Storefront/storefront/component/line-item/element/quantity.html.twig', [
            'lineItem' => $lineItem,
            'displayMode' => 'offcanvas',
            'nestingLevel' => 0,
        ]);

        $quantityInput = (new Crawler($html))->filter('input.js-quantity-selector');

        static::assertCount(1, $quantityInput);
        static::assertSame($expectsDisabled, $quantityInput->attr('disabled') !== null);
    }

    public static function lineItemCases(): \Generator
    {
        yield 'digital product limited to one unit disables the quantity select' => [ProductDefinition::TYPE_DIGITAL, 1, true];
        yield 'digital product with a max purchase above 1 enables the quantity select' => [ProductDefinition::TYPE_DIGITAL, 5, false];
        yield 'physical product limited to one unit disables the quantity select' => [ProductDefinition::TYPE_PHYSICAL, 1, true];
        yield 'physical product with a max purchase above 1 enables the quantity select' => [ProductDefinition::TYPE_PHYSICAL, 5, false];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function render(string $template, array $parameters): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig->render($template, $parameters);
    }
}
