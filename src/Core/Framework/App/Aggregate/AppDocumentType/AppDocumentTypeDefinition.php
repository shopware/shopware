<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppDocumentType;

use Shopware\Core\Framework\App\Aggregate\AppDocumentTypeTranslation\AppDocumentTypeTranslationDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppDocumentTypeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_document_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppDocumentTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppDocumentTypeEntity::class;
    }

    public function since(): string
    {
        return '6.7.13.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    /**
     * TODO: Intentionally disabled default timestamps for now so `createdAt` / `updatedAt` stay
     */
    protected function defaultFields(): array
    {
        return [];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new CascadeDelete(), new Required()),

            (new StringField('technical_name', 'technicalName'))->addFlags(new Required()),
            new JsonField('config', 'config'),
            new JsonField('formats', 'formats'),
            new TranslatedField('label'),

            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
            (new TranslationsAssociationField(AppDocumentTypeTranslationDefinition::class, 'app_document_type_id'))->addFlags(new Required()),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
