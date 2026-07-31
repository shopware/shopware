<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppDocumentTypeTranslation;

use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppDocumentTypeTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'app_document_type_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppDocumentTypeTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppDocumentTypeTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.13.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return AppDocumentTypeDefinition::class;
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
            (new StringField('label', 'label'))->addFlags(new Required()),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
