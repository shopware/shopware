<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ContentSystem\Mapping\ProductMediaCollectionToMediaCollectionProjection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductMediaCollectionToMediaCollectionProjection::class)]
class ProductMediaCollectionToMediaCollectionProjectionTest extends TestCase
{
    public function testDeclaresTheTypesItBridges(): void
    {
        $projection = new ProductMediaCollectionToMediaCollectionProjection();

        static::assertSame('product_media_collection_to_media_collection', $projection->name());
        static::assertSame(ProductMediaCollection::class, $projection->inputType());
        static::assertSame(MediaCollection::class, $projection->outputType());
    }

    #[TestDox('unwraps every assignment to its picture, in the merchant-authored position order')]
    public function testUnwrapsTheAssignmentsInPositionOrder(): void
    {
        $second = $this->assignment(position: 1);
        $first = $this->assignment(position: 0);

        $media = (new ProductMediaCollectionToMediaCollectionProjection())
            ->project(new ProductMediaCollection([$second, $first]));

        static::assertInstanceOf(MediaCollection::class, $media);
        static::assertSame(
            [$first->getMedia(), $second->getMedia()],
            array_values($media->getElements())
        );
    }

    /**
     * A null would have to be guarded against in every gallery template, and the assignment carries no picture
     * to show in its place.
     */
    public function testSkipsAnAssignmentWhosePictureIsNotLoaded(): void
    {
        $loaded = $this->assignment(position: 0);
        $unloaded = new ProductMediaEntity();
        $unloaded->setUniqueIdentifier(Uuid::randomHex());
        $unloaded->setPosition(1);

        $media = (new ProductMediaCollectionToMediaCollectionProjection())
            ->project(new ProductMediaCollection([$loaded, $unloaded]));

        static::assertSame([$loaded->getMedia()], array_values($media->getElements()));
    }

    /**
     * Not null: a product with no images renders an empty gallery, and delivering nothing would instead fall
     * back to the authored images, which belong to no product on the page.
     */
    public function testAnEmptyInputYieldsAnEmptyCollectionRatherThanNull(): void
    {
        $media = (new ProductMediaCollectionToMediaCollectionProjection())->project(new ProductMediaCollection());

        static::assertCount(0, $media);
    }

    /**
     * The input is the ambient product's own association, shared with everything else reading it this render.
     */
    public function testLeavesTheInputCollectionsOrderAlone(): void
    {
        $second = $this->assignment(position: 1);
        $first = $this->assignment(position: 0);
        $input = new ProductMediaCollection([$second, $first]);

        (new ProductMediaCollectionToMediaCollectionProjection())->project($input);

        static::assertSame([$second, $first], array_values($input->getElements()));
    }

    private function assignment(int $position): ProductMediaEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier(Uuid::randomHex());

        $assignment = new ProductMediaEntity();
        $assignment->setUniqueIdentifier(Uuid::randomHex());
        $assignment->setPosition($position);
        $assignment->setMedia($media);

        return $assignment;
    }
}
