<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\Mapping;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentPropertyProjection;
use Shopware\Core\Framework\Log\Package;

/**
 * Unwraps one product image assignment to the image itself, so a product's cover can fill a plain
 * `MediaEntity` property such as `Sw:Media:Image.media`.
 *
 * `product.cover` is a {@see ProductMediaEntity} — the ASSIGNMENT record (position, cover flag, the product it
 * belongs to) rather than the picture. Every element that wants a picture declares `MediaEntity`, so without
 * this hop the cover is unmappable, and a template handed the assignment would find no url and no thumbnails.
 *
 * @internal
 */
#[Package('inventory')]
class ProductMediaToMediaProjection extends AbstractContentPropertyProjection
{
    final public const NAME = 'product_media_to_media';

    public function name(): string
    {
        return self::NAME;
    }

    public function inputType(): string
    {
        return ProductMediaEntity::class;
    }

    public function outputType(): string
    {
        return MediaEntity::class;
    }

    /**
     * A null `media` is the assignment's own association being unloaded rather than an empty picture, and
     * returning it makes the delivery layer treat the mapping as having resolved to nothing — which falls
     * back to whatever the author picked, exactly as an absent cover does.
     */
    public function project(mixed $value): ?MediaEntity
    {
        \assert($value instanceof ProductMediaEntity);

        return $value->getMedia();
    }
}
