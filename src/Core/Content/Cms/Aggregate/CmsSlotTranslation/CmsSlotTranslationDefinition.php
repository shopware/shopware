<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\DataAbstractionLayer\Field\SlotConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CmsSlotTranslationDefinition extends EntityTranslationDefinition
{
    public static function getEntityName(): string
    {
        return 'cms_slot_translation';
    }

    public static function getParentDefinitionClass(): string
    {
        return CmsSlotDefinition::class;
    }

    public static function getEntityClass(): string
    {
        return CmsSlotTranslationEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new SlotConfigField('config', 'config'))->setFlags(new Required()),
            new AttributesField(),
        ]);
    }
}
