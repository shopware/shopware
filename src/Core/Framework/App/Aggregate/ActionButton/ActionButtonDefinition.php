<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButton;

use Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class ActionButtonDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'app_action_button';

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

    /**
     * @feature-deprecated (FEATURE_NEXT_14360) tag:v6.5.0 - Will be remove on version 6.5.0.
     * It will no longer be used in the manifest.xml file
     * and will be processed in the Executor with an OpenNewTabResponse response instead.
     */
    public function getDefaults(): array
    {
        return ['openNewTab' => false];
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('entity', 'entity'))->addFlags(new Required()),
            (new StringField('view', 'view'))->addFlags(new Required()),
            (new StringField('url', 'url'))->addFlags(new Required()),
            (new StringField('action', 'action'))->addFlags(new Required()),
            /*
             * @feature-deprecated (FEATURE_NEXT_14360) tag:v6.5.0 - openNewTab field will be remove on version 6.5.0.
             * It will no longer be used in the manifest.xml file
             * and will be processed in the Executor with an OpenNewTabResponse response instead.
             */
            (new BoolField('open_new_tab', 'openNewTab'))->addFlags(new Required()),
            new TranslatedField('label'),
            (new TranslationsAssociationField(ActionButtonTranslationDefinition::class, 'app_action_button_id'))->addFlags(new Required()),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
