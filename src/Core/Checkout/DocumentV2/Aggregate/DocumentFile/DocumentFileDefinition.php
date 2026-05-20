<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile;

use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * Stores one persisted artifact of a generated document in a specific format.
 *
 * A single document can have multiple document_file rows, for example when the caller asked
 * for HTML and PDF output for the same document number. Intermediate dependency formats that
 * only exist during rendering are not stored here.
 *
 * @internal
 */
#[Package('after-sales')]
class DocumentFileDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'document_file';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DocumentFileCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentFileEntity::class;
    }

    public function since(): string
    {
        return '6.7.10.0';
    }

    /**
     * TODO: Intentionally disabled default timestamps for now so `createdAt` / `updatedAt` stay
     * non-ApiAware while `document_file` is still internal.
     * Remove this override `defaultFields()` and remove explicit timestamp fields
     * (CreatedAtField, UpdatedAtField) once the public API fields are finalized.
     */
    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('document_id', 'documentId', DocumentDefinition::class))->addFlags(new Required()),
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new Required()),

            (new StringField('document_format', 'documentFormat', 255))->addFlags(new Required()),

            new ManyToOneAssociationField('document', 'document_id', DocumentDefinition::class, 'id', false),
            new OneToOneAssociationField('media', 'media_id', 'id', MediaDefinition::class),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
