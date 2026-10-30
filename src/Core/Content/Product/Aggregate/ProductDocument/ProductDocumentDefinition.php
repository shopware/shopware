<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductDocumentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_document';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductDocumentCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductDocumentEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.17.0';
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

            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('The order in which the documents of a product are displayed.'),
            (new TranslatedField('title'))->addFlags(new ApiAware())->setDescription('Display name of the document.'),
            (new TranslatedField('mediaId'))->addFlags(new ApiAware())->setDescription('Unique identity of the file of the document.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),

            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id'))->addFlags(new ApiAware(), new ReverseInherited('documents')),
            (new TranslationsAssociationField(ProductDocumentTranslationDefinition::class, 'product_document_id'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
