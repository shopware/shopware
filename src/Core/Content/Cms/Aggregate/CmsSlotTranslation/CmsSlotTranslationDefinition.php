<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\DataAbstractionLayer\Field\SlotConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CmsSlotTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'cms_slot_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CmsSlotTranslationEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return CmsSlotDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new SlotConfigField('config', 'config'))->setFlags(new Required()),
            new CustomFields(),
        ]);
    }
}
