<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppDocumentTypeTranslation;

use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

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

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
