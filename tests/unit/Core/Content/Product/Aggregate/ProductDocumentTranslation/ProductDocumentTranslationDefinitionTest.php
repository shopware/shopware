<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocumentTranslation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentTranslationDefinition::class)]
class ProductDocumentTranslationDefinitionTest extends TestCase
{
    private ProductDocumentTranslationDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                ProductDocumentTranslationDefinition::class,
                ProductDocumentDefinition::class,
                ProductDefinition::class,
                MediaDefinition::class,
                LanguageDefinition::class,
                VersionDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $definition = $registry->getByEntityName(ProductDocumentTranslationDefinition::ENTITY_NAME);
        static::assertInstanceOf(ProductDocumentTranslationDefinition::class, $definition);

        $this->definition = $definition;
    }

    public function testDescribesItsEntity(): void
    {
        static::assertSame('product_document_translation', $this->definition->getEntityName());
        static::assertSame('6.7.17.0', $this->definition->since());
        static::assertSame(ProductDocumentTranslationEntity::class, $this->definition->getEntityClass());
        static::assertSame(ProductDocumentTranslationCollection::class, $this->definition->getCollectionClass());
        static::assertInstanceOf(ProductDocumentDefinition::class, $this->definition->getParentDefinition());
    }

    public function testIsVersionedLikeTheDocument(): void
    {
        $versionId = $this->definition->getFields()->get('productDocumentVersionId');

        static::assertInstanceOf(ReferenceVersionField::class, $versionId);
        static::assertSame(ProductDocumentDefinition::class, $versionId->getVersionReferenceClass());
    }

    public function testNameIsRequired(): void
    {
        $name = $this->definition->getFields()->get('name');

        static::assertInstanceOf(StringField::class, $name);
        static::assertTrue($name->is(Required::class));
        static::assertTrue($name->is(ApiAware::class));
    }

    public function testFileIsRequiredAndReferencesMedia(): void
    {
        $fields = $this->definition->getFields();

        $mediaId = $fields->get('mediaId');
        static::assertInstanceOf(FkField::class, $mediaId);
        static::assertSame('media_id', $mediaId->getStorageName());
        static::assertTrue($mediaId->is(Required::class));
        static::assertTrue($mediaId->is(ApiAware::class));

        $media = $fields->get('media');
        static::assertInstanceOf(ManyToOneAssociationField::class, $media);
        static::assertSame(MediaDefinition::class, $media->getReferenceClass());
        static::assertFalse($media->getAutoload());
        static::assertTrue($media->is(ApiAware::class));
    }

    public function testHasCustomFields(): void
    {
        $customFields = $this->definition->getFields()->get('customFields');

        static::assertInstanceOf(CustomFields::class, $customFields);
        static::assertTrue($customFields->is(ApiAware::class));
    }
}
