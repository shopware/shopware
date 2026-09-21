<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\Mapping;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentPropertyProjection;
use Shopware\Core\Framework\Log\Package;

/**
 * Unwraps a product's image assignments to the images themselves, so `product.media` can fill a plain
 * `MediaCollection` property such as the gallery's `mediaItems`.
 *
 * This is the case a dotted path structurally cannot express: `product.media` is a collection of ASSIGNMENT
 * records, each holding its picture one level further in, and getting at the pictures is a loop over the
 * collection rather than another hop along a path.
 *
 * Order is the merchant's: the positions authored in the product's media tab, so the gallery shows the images
 * in the order the product page does rather than in whatever order the association came back in.
 *
 * @internal
 */
#[Package('inventory')]
class ProductMediaCollectionToMediaCollectionProjection extends AbstractContentPropertyProjection
{
    final public const NAME = 'product_media_collection_to_media_collection';

    public function name(): string
    {
        return self::NAME;
    }

    public function inputType(): string
    {
        return ProductMediaCollection::class;
    }

    public function outputType(): string
    {
        return MediaCollection::class;
    }

    /**
     * Sorts a copy of the element list rather than calling `sort()` on the input: the input is the ambient
     * product's own association, shared by every element of the render, and reordering it in place would
     * reach anything else reading it.
     *
     * An assignment whose `media` is not loaded is skipped rather than contributing a null the gallery
     * template would have to guard against. An input that yields nothing still returns an EMPTY collection
     * and not null, because a product with no images has a known answer — the gallery renders empty — and
     * falling back to the authored images there would show pictures belonging to a different product.
     */
    public function project(mixed $value): MediaCollection
    {
        \assert($value instanceof ProductMediaCollection);

        $assignments = array_values($value->getElements());

        usort(
            $assignments,
            static fn (ProductMediaEntity $a, ProductMediaEntity $b): int => $a->getPosition() <=> $b->getPosition()
        );

        $media = new MediaCollection();

        foreach ($assignments as $assignment) {
            $entity = $assignment->getMedia();

            if ($entity === null) {
                continue;
            }

            $media->add($entity);
        }

        return $media;
    }
}
