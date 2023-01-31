<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
class DocumentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'document';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DocumentCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('document_type_id', 'documentTypeId', DocumentTypeDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new StringField('file_type', 'fileType'))->addFlags(new ApiAware(), new Required()),
            (new FkField('referenced_document_id', 'referencedDocumentId', self::class))->addFlags(new ApiAware()),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('document_media_file_id', 'documentMediaFileId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new ApiAware(), new Required()),

            (new JsonField('config', 'config', [], []))->addFlags(new ApiAware(), new Required()),
            (new BoolField('sent', 'sent'))->addFlags(new ApiAware()),
            (new BoolField('static', 'static'))->addFlags(new ApiAware()),
            (new StringField('deep_link_code', 'deepLinkCode'))->addFlags(new ApiAware(), new Required()),
            (new CustomFields())->addFlags(new ApiAware()),

            (new ManyToOneAssociationField('documentType', 'document_type_id', DocumentTypeDefinition::class, 'id'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('referencedDocument', 'referenced_document_id', self::class, 'id', false))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('dependentDocuments', self::class, 'referenced_document_id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('documentMediaFile', 'document_media_file_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);
    }
}
