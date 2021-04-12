<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation;

use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 */
class AppCmsBlockTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'app_cms_block_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppCmsBlockTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppCmsBlockTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.4.2.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return AppCmsBlockDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
        ]);
    }
}
