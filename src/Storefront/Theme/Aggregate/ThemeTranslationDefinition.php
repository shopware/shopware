<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Storefront\Theme\ThemeDefinition;

class ThemeTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'theme_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ThemeTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return ThemeTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ThemeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('description', 'description'),
            new JsonField('labels', 'labels'),
            new JsonField('help_texts', 'helpTexts'),
            new CustomFields(),
        ]);
    }
}
