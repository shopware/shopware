<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppScriptConditionTranslation;

use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AppScriptConditionTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'app_script_condition_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppScriptConditionTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppScriptConditionTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.10.3';
    }

    protected function getParentDefinitionClass(): string
    {
        return AppScriptConditionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
