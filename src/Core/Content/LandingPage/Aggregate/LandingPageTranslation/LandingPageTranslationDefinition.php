<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation;

use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class LandingPageTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'landing_page_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LandingPageTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return LandingPageTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return LandingPageDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            (new StringField('url', 'url'))->addFlags(new ApiAware(), new Required()),
            (new JsonField('slot_config', 'slotConfig'))->addFlags(new ApiAware()),
            (new LongTextField('meta_title', 'metaTitle'))->addFlags(new ApiAware(), new AllowHtml()),
            (new LongTextField('meta_description', 'metaDescription'))->addFlags(new ApiAware(), new AllowHtml()),
            (new LongTextField('keywords', 'keywords'))->addFlags(new ApiAware(), new AllowHtml()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
