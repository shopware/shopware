<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentCollection::class)]
class ProductDocumentCollectionTest extends TestCase
{
    public function testApiAlias(): void
    {
        static::assertSame('product_document_collection', (new ProductDocumentCollection())->getApiAlias());
    }

    public function testCollectionAllowsProductDocuments(): void
    {
        $entity = new ProductDocumentEntity();
        $entity->setId('product-document-id');

        $collection = new ProductDocumentCollection([$entity]);

        static::assertSame($entity, $collection->get('product-document-id'));
    }

    public function testCollectionRejectsInvalidElements(): void
    {
        static::expectExceptionObject(FrameworkException::collectionElementInvalidType(ProductDocumentEntity::class, \stdClass::class));

        /** @phpstan-ignore argument.type */
        new ProductDocumentCollection([new \stdClass()]);
    }
}
