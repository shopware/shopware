<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButton;

use Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class ActionButtonDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_action_button';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ActionButtonCollection::class;
    }

    public function getEntityClass(): string
    {
        return ActionButtonEntity::class;
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('entity', 'entity'))->addFlags(new Required()),
            (new StringField('view', 'view'))->addFlags(new Required()),
            (new StringField('url', 'url'))->addFlags(new Required()),
            (new StringField('action', 'action'))->addFlags(new Required()),
            new TranslatedField('label'),
            (new TranslationsAssociationField(ActionButtonTranslationDefinition::class, 'app_action_button_id'))->addFlags(new Required()),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
