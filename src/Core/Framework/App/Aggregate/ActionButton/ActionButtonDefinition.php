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
 * @internal only for use by the app-system
 */
#[Package('framework')]
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
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of app\'s action button.'),
            (new StringField('entity', 'entity'))->addFlags(new Required())->setDescription('Indicates in which particular entity.'),
            (new StringField('view', 'view'))->addFlags(new Required())->setDescription('Indicates whether it is detail page view or listing page view.'),
            (new StringField('url', 'url'))->addFlags(new Required())->setDescription('An url associated with the AppActionButton.'),
            (new StringField('action', 'action'))->addFlags(new Required())->setDescription('Indicates the action or functionality that the button performs when clicked or interacted with.'),
            new TranslatedField('label'),
            (new TranslationsAssociationField(ActionButtonTranslationDefinition::class, 'app_action_button_id'))->addFlags(new Required()),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new Required())->setDescription('Unique identity of app.'),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
