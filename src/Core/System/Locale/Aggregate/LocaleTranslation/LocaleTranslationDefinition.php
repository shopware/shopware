<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Aggregate\LocaleTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LocaleDefinition;

#[Package('system-settings')]
class LocaleTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'locale_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LocaleTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return LocaleTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return LocaleDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            (new StringField('territory', 'territory'))->addFlags(new ApiAware(), new Required()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
