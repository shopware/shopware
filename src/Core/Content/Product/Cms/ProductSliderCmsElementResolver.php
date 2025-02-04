<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\Cms\ProductSlider\AbstractProductSliderProcessor;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class ProductSliderCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @var array<int|string, AbstractProductSliderProcessor>
     */
    private array $processors = [];

    /**
     * @param iterable<AbstractProductSliderProcessor> $processors
     *
     * @internal
     */
    public function __construct(iterable $processors)
    {
        foreach ($processors as $handler) {
            $this->processors[$handler->getSource()] = $handler;
        }
    }

    public function getType(): string
    {
        return 'product-slider';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $productConfig = $config->get('products');

        if (!$productConfig || !$productConfig->getValue()) {
            return null;
        }

        $source = $productConfig->getSource();
        $processor = $this->processors[$source] ?? null;

        return $processor?->collect($slot, $config, $resolverContext);
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $slider = new ProductSliderStruct();
        $slot->setData($slider);

        $productConfig = $config->get('products');

        if (!$productConfig) {
            return;
        }

        $source = $productConfig->getSource();
        $processor = $this->processors[$source] ?? null;

        if (!$processor) {
            return;
        }

        $processor->enrich($slot, $result, $resolverContext);
    }
}
