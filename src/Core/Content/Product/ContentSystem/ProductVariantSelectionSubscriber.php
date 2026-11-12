<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem;

use Shopware\Core\Content\Product\SalesChannel\Detail\PartialProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorProductData;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\RenderedTreeEditor;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Configurator options depend on the product that won mapping and fallback delivery, not its saved picker ID.
 * Finalization enriches that product's component after delivery without changing the general loading order.
 *
 * @internal
 */
#[Package('discovery')]
class ProductVariantSelectionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly PartialProductConfiguratorLoader $configuratorLoader)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [RenderedTreeFinalizationEvent::class => 'onRenderedTreeFinalization'];
    }

    public function onRenderedTreeFinalization(RenderedTreeFinalizationEvent $event): void
    {
        /** @var array<string, PropertyGroupCollection> $options */
        $options = [];

        $event->replaceTree((new RenderedTreeEditor())->mapNodes($event->tree(), function (RenderedElement $element) use ($event, &$options): RenderedElement {
            // Skeletons carry no properties and must not load data or gain values during finalization.
            if ($element->component !== 'Sw:Product:VariantSelection' || $element->properties === []) {
                return $element;
            }

            $product = $element->properties['product'] ?? null;
            if (!$product instanceof SalesChannelProductEntity) {
                return $element->withProperty('configuratorSettings', null);
            }

            $id = $product->getId();
            $parentId = $product->getParentId() ?? $id;
            if (!isset($options[$id])) {
                $options[$id] = $this->configuratorLoader->load(new ProductConfiguratorProductData(
                    $id,
                    $parentId,
                    $product->getOptionIds(),
                    $product->getVariantListingConfig(),
                ), $event->salesChannelContext);
                $event->cacheContext->addTags([
                    EntityCacheKeyGenerator::buildProductTag($id),
                    EntityCacheKeyGenerator::buildProductTag($parentId),
                ]);
            }

            return $element->withProperty('configuratorSettings', $options[$id]);
        }));
    }
}
