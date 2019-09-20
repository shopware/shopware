<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
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

class DocumentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'document';

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

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('document_type_id', 'documentTypeId', DocumentTypeDefinition::class))->addFlags(new Required()),
            (new StringField('file_type', 'fileType'))->addFlags(new Required()),

            new FkField('referenced_document_id', 'referencedDocumentId', self::class),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            new FkField('document_media_file_id', 'documentMediaFileId', MediaDefinition::class),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),

            (new JsonField('config', 'config', [], []))->addFlags(new Required()),
            new BoolField('sent', 'sent'),
            new BoolField('static', 'static'),
            (new StringField('deep_link_code', 'deepLinkCode'))->addFlags(new Required()),

            new CustomFields(),

            (new ManyToOneAssociationField('documentType', 'document_type_id', DocumentTypeDefinition::class, 'id'))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('referencedDocument', 'referenced_document_id', self::class, 'id', false),
            new OneToManyAssociationField('dependentDocuments', self::class, 'referenced_document_id'),
            new ManyToOneAssociationField('documentMediaFile', 'document_media_file_id', MediaDefinition::class, 'id', false),
        ]);
    }
}
