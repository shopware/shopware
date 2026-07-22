<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Profile;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportV2ProfileDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'import_export_v2_profile';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ImportExportV2ProfileCollection::class;
    }

    public function getEntityClass(): string
    {
        return ImportExportV2ProfileEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('entity_name', 'entity'))->addFlags(new Required()),
            (new StringField('format', 'format'))->addFlags(new Required()),
            (new JsonField('identifier_paths', 'identifierPaths'))->addFlags(new Required()),
            (new JsonField('payload_paths', 'payloadPaths'))->addFlags(new Required()),
            (new JsonField('relation_modes', 'relationModes'))->addFlags(new Required()),
            (new JsonField('field_mappings', 'fieldMappings'))->addFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
