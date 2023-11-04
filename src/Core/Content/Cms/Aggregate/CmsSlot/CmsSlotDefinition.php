<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSlot;

use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class CmsSlotDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'cms_slot';

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
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new ApiAware()),

            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required()),
            (new StringField('slot', 'slot'))->addFlags(new ApiAware(), new Required()),
            (new LockedField())->addFlags(new ApiAware()),
            (new TranslatedField('config'))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),

            (new JsonField('data', 'data'))->addFlags(new ApiAware(), new Runtime(), new WriteProtected()),

            (new FkField('cms_block_id', 'blockId', CmsBlockDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ManyToOneAssociationField('block', 'cms_block_id', CmsBlockDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(CmsSlotTranslationDefinition::class, 'cms_slot_id'))->addFlags(new ApiAware()),
        ]);

        $collection->add((new JsonField('fieldConfig', 'fieldConfig'))->addFlags(new Runtime(), new ApiAware()));

        $collection->add((new ReferenceVersionField(CmsBlockDefinition::class))->addFlags(new Required(), new ApiAware()));

        return $collection;
    }
}
