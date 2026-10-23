<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductDocumentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_document';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.7.17.0';
    }

    public function getCollectionClass(): string
    {
        return ProductDocumentCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductDocumentEntity::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ProductDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the product document.'),
            (new VersionField())->addFlags(new ApiAware()),

            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the product.'),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('The order in which the product documents are displayed.'),
            (new TranslatedField('name'))->addFlags(new ApiAware())->setDescription('Display name of the document.'),
            (new TranslatedField('mediaId'))->addFlags(new ApiAware())->setDescription('Unique identity of the file of the document, falling back to the default language.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),

            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'))->addFlags(new ApiAware(), new ReverseInherited('productDocuments')),
            // The file of the current language is stored in the translation; it is resolved after loading by the ProductDocumentMediaSubscriber.
            (new OneToOneAssociationField('media', 'media', 'id', MediaDefinition::class, false))->addFlags(new ApiAware(), new Runtime())->setDescription('File of the document in the current language, falling back to the default language.'),
            (new TranslationsAssociationField(ProductDocumentTranslationDefinition::class, 'product_document_id'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
