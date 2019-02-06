<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation;

use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class CmsPageTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'cms_page_translation';
    }

    public static function getEntityClass(): string
    {
        return CmsPageTranslationEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return CmsPageDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new Required()),
            new AttributesField(),
        ]);
    }
}
