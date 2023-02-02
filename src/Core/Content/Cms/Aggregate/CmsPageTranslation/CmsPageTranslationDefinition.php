<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation;

use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class CmsPageTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'cms_page_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CmsPageTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return CmsPageDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name')),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
