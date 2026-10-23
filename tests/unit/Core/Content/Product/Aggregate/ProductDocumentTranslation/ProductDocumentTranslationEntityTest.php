<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocumentTranslation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationEntity;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentTranslationEntity::class)]
#[CoversClass(ProductDocumentTranslationCollection::class)]
class ProductDocumentTranslationEntityTest extends TestCase
{
    public function testAccessors(): void
    {
        $document = new ProductDocumentEntity();
        $media = new MediaEntity();

        $translation = new ProductDocumentTranslationEntity();
        $translation->setProductDocumentId('document-id');
        $translation->setProductDocumentVersionId('document-version-id');
        $translation->setName('Handbuch');
        $translation->setMediaId('media-id');
        $translation->setCustomFields(['foo' => 'bar']);
        $translation->setProductDocument($document);
        $translation->setMedia($media);

        static::assertSame('document-id', $translation->getProductDocumentId());
        static::assertSame('document-version-id', $translation->getProductDocumentVersionId());
        static::assertSame('Handbuch', $translation->getName());
        static::assertSame('media-id', $translation->getMediaId());
        static::assertSame(['foo' => 'bar'], $translation->getCustomFields());
        static::assertSame($document, $translation->getProductDocument());
        static::assertSame($media, $translation->getMedia());
    }

    public function testCollectionApiAlias(): void
    {
        static::assertSame('product_document_translation_collection', (new ProductDocumentTranslationCollection())->getApiAlias());
    }

    public function testCollectionRejectsInvalidElements(): void
    {
        static::expectExceptionObject(FrameworkException::collectionElementInvalidType(ProductDocumentTranslationEntity::class, \stdClass::class));

        /** @phpstan-ignore argument.type */
        new ProductDocumentTranslationCollection([new \stdClass()]);
    }
}
