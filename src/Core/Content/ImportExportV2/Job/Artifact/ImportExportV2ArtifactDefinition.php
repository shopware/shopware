<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Artifact;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class ImportExportV2ArtifactDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'import_export_v2_artifact';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ImportExportV2ArtifactCollection::class;
    }

    public function getEntityClass(): string
    {
        return ImportExportV2ArtifactEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('mime_type', 'mimeType'))->addFlags(new Required()),
            (new LongTextField('contents', 'contents'))->addFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
