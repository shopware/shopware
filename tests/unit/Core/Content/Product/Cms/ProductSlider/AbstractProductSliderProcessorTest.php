<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms\ProductSlider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\Cms\ProductSlider\AbstractProductSliderProcessor;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractProductSliderProcessor::class)]
class AbstractProductSliderProcessorTest extends TestCase
{
    use ProductSliderUnitTrait;

    public function testGetDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);
        (new TestAbstractProductSliderProcessor())->getDecorated();
    }

    public function testGetSource(): void
    {
        $processor = new TestAbstractProductSliderProcessor();
        static::assertSame('test', $processor->getSource());
    }

    public function testFilterOutOutOfStockHiddenCloseoutProducts(): void
    {
        $products = $this->getProducts();
        static::assertCount(2, $products);

        $processor = new TestAbstractProductSliderProcessor();

        $filteredProducts = $processor->publicFilterOutOutOfStockHiddenCloseoutProducts($products);
        static::assertCount(1, $filteredProducts);
    }
}

/**
 * @internal
 */
class TestAbstractProductSliderProcessor extends AbstractProductSliderProcessor
{
    public function getDecorated(): AbstractProductSliderProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function getSource(): string
    {
        return 'test';
    }

    public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void
    {
        // nth
    }

    public function publicFilterOutOutOfStockHiddenCloseoutProducts(ProductCollection $products): ProductCollection
    {
        return $this->filterOutOutOfStockHiddenCloseoutProducts($products);
    }
}
