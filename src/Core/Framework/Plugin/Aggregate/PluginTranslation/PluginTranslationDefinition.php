<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Plugin\PluginDefinition;

class PluginTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'plugin_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PluginTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return PluginTranslationEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return PluginDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
            new LongTextWithHtmlField('description', 'description'),
            new StringField('manufacturer_link', 'manufacturerLink'),
            new StringField('support_link', 'supportLink'),
            new JsonField('changelog', 'changelog'),
            new CustomFields(),
        ]);
    }
}
