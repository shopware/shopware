<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSlot;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CmsSlotDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'cms_slot';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CmsSlotEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CmsSlotCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CmsBlockDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new StringField('type', 'type'))->addFlags(new Required()),
            (new StringField('slot', 'slot'))->addFlags(new Required()),

            new LockedField(),

            new TranslatedField('config'),
            new TranslatedField('customFields'),

            (new JsonField('data', 'data'))->addFlags(new Runtime(), new WriteProtected()),

            (new FkField('cms_block_id', 'blockId', CmsBlockDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('block', 'cms_block_id', CmsBlockDefinition::class, 'id', false),

            new TranslationsAssociationField(CmsSlotTranslationDefinition::class, 'cms_slot_id'),
        ]);
    }
}
