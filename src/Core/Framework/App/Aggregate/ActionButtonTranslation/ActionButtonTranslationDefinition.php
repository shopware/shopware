<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class ActionButtonTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'app_action_button_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ActionButtonTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ActionButtonTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ActionButtonDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
        ]);
    }
}
