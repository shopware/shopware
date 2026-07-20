<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentDefinition::class)]
class ProductDocumentDefinitionTest extends TestCase
{
    private ProductDocumentDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                ProductDocumentDefinition::class,
                ProductDefinition::class,
                MediaDefinition::class,
                VersionDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(ProductDocumentDefinition::ENTITY_NAME);
        static::assertInstanceOf(ProductDocumentDefinition::class, $definition);

        $this->definition = $definition;
    }

    public function testEntityName(): void
    {
        static::assertSame(ProductDocumentDefinition::ENTITY_NAME, $this->definition->getEntityName());
    }

    public function testSince(): void
    {
        static::assertSame('6.7.11.0', $this->definition->since());
    }

    public function testEntityClass(): void
    {
        static::assertSame(ProductDocumentEntity::class, $this->definition->getEntityClass());
    }

    public function testCollectionClass(): void
    {
        static::assertSame(ProductDocumentCollection::class, $this->definition->getCollectionClass());
    }

    public function testParentDefinitionClass(): void
    {
        $method = new \ReflectionMethod(ProductDocumentDefinition::class, 'getParentDefinitionClass');

        static::assertSame(ProductDefinition::class, $method->invoke($this->definition));
    }

    public function testFields(): void
    {
        $fields = $this->definition->getFields();

        $id = $fields->get('id');
        static::assertInstanceOf(IdField::class, $id);
        static::assertTrue($id->is(ApiAware::class));
        static::assertTrue($id->is(PrimaryKey::class));
        static::assertTrue($id->is(Required::class));

        $versionId = $fields->get('versionId');
        static::assertInstanceOf(VersionField::class, $versionId);
        static::assertTrue($versionId->is(ApiAware::class));

        $productId = $fields->get('productId');
        static::assertInstanceOf(FkField::class, $productId);
        static::assertSame('product_id', $productId->getStorageName());
        static::assertTrue($productId->is(ApiAware::class));
        static::assertTrue($productId->is(Required::class));

        $productVersionId = $fields->get('productVersionId');
        static::assertInstanceOf(ReferenceVersionField::class, $productVersionId);
        static::assertSame(ProductDefinition::class, $productVersionId->getVersionReferenceClass());
        static::assertTrue($productVersionId->is(ApiAware::class));
        static::assertTrue($productVersionId->is(Required::class));

        $mediaId = $fields->get('mediaId');
        static::assertInstanceOf(FkField::class, $mediaId);
        static::assertSame('media_id', $mediaId->getStorageName());
        static::assertTrue($mediaId->is(ApiAware::class));
        static::assertTrue($mediaId->is(Required::class));

        $position = $fields->get('position');
        static::assertInstanceOf(IntField::class, $position);
        static::assertTrue($position->is(ApiAware::class));

        $product = $fields->get('product');
        static::assertInstanceOf(ManyToOneAssociationField::class, $product);
        static::assertSame(ProductDefinition::class, $product->getReferenceClass());
        static::assertTrue($product->is(ApiAware::class));

        $reverseInherited = $product->getFlag(ReverseInherited::class);
        static::assertInstanceOf(ReverseInherited::class, $reverseInherited);
        static::assertSame('productDocuments', $reverseInherited->getReversedPropertyName());

        $media = $fields->get('media');
        static::assertInstanceOf(ManyToOneAssociationField::class, $media);
        static::assertSame(MediaDefinition::class, $media->getReferenceClass());
        static::assertTrue($media->is(ApiAware::class));
    }
}
