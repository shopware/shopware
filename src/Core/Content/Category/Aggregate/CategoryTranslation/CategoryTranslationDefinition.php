<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CategoryTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'category_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CategoryTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CategoryTranslationCollection::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return CategoryDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new ListField('breadcrumb', 'breadcrumb', StringField::class))->addFlags(new WriteProtected()),
            new JsonField('slot_config', 'slotConfig'),
            new StringField('external_link', 'externalLink'),
            (new LongTextField('description', 'description'))->addFlags(new AllowHtml()),
            (new LongTextField('meta_title', 'metaTitle'))->addFlags(new AllowHtml()),
            (new LongTextField('meta_description', 'metaDescription'))->addFlags(new AllowHtml()),
            (new LongTextField('keywords', 'keywords'))->addFlags(new AllowHtml()),

            new CustomFields(),
        ]);
    }
}
