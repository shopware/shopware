<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class BuyButtonComponentTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testIsRegisteredAsElementTypeReadingTheProductFromContext(): void
    {
        $types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $types);

        $product = $types->get('Sw:Product:BuyButton')->properties()['product'] ?? null;

        static::assertNotNull($product);
        static::assertSame(SalesChannelProductEntity::class, $product->toSchema()['type']);
    }

    public function testRendersAddToCartFormForBuyableProduct(): void
    {
        $product = $this->product(minPurchase: 2);

        $html = $this->render(['product' => $product]);

        static::assertStringContainsString('data-component="Sw:Product:BuyButton"', $html);
        static::assertStringContainsString('is--buyable', $html);
        static::assertStringContainsString('name="lineItems[' . $product->getId() . '][id]"', $html);
        static::assertMatchesRegularExpression(
            '/name="lineItems\[' . $product->getId() . '\]\[quantity\]"\s+value="2"/',
            $html,
        );
    }

    public function testRendersNoFormForParentProductWithVariants(): void
    {
        $html = $this->render(['product' => $this->product(childCount: 3)]);

        static::assertStringNotContainsString('<form', $html);
        static::assertStringNotContainsString('is--buyable', $html);
    }

    public function testRendersNoFormForUnavailableProduct(): void
    {
        $html = $this->render(['product' => $this->product(available: false)]);

        static::assertStringNotContainsString('<form', $html);
        static::assertStringNotContainsString('is--buyable', $html);
    }

    public function testShowsFallbackOnlyInPreviewWhenProductIsMissing(): void
    {
        $storefront = $this->render([]);
        $preview = $this->render(['preview' => true]);

        static::assertStringNotContainsString('<form', $storefront);
        static::assertStringNotContainsString('No longer available', $storefront);
        static::assertStringContainsString('No longer available', $preview);
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(array $props): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig
            ->createTemplate('{{ component(\'Sw:Product:BuyButton\', props) }}')
            ->render(['props' => $props]);
    }

    private function product(bool $available = true, int $childCount = 0, int $minPurchase = 1): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setAvailable($available);
        $product->setChildCount($childCount);
        $product->setMinPurchase($minPurchase);
        $product->setCalculatedMaxPurchase(100);
        $product->setTranslated(['name' => 'Buyable product']);

        return $product;
    }
}
