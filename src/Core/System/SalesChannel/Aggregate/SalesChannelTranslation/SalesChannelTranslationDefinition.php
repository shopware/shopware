<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('sales-channel')]
class SalesChannelTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'sales_channel_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalesChannelTranslationEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'homeEnabled' => true,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return SalesChannelDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            new JsonField('home_slot_config', 'homeSlotConfig'),
            (new BoolField('home_enabled', 'homeEnabled'))->addFlags(new Required()),
            new StringField('home_name', 'homeName'),
            new StringField('home_meta_title', 'homeMetaTitle'),
            new StringField('home_meta_description', 'homeMetaDescription'),
            new StringField('home_keywords', 'homeKeywords'),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);

        return $fields;
    }
}
