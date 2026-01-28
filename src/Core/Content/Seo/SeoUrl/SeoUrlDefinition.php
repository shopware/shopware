<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrl;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('inventory')]
class SeoUrlDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'seo_url';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SeoUrlCollection::class;
    }

    public function getEntityClass(): string
    {
        return SeoUrlEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of Seo Url.'),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of sales channel.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of language.'),
            (new IdField('foreign_key', 'foreignKey'))->addFlags(new ApiAware(), new Required())->setDescription('The key that references to product or category entity ID.'),

            (new StringField('route_name', 'routeName', 50))->addFlags(new ApiAware(), new Required())->setDescription('A destination routeName that has been registered somewhere in the app\'s router. For example: \\\"frontend.detail.page\\\"'),
            (new StringField('path_info', 'pathInfo', 750))->addFlags(new ApiAware(), new Required())->setDescription('Path to product URL. For example: \\\"/detail/bbf36734504741c79a3bbe3795b91564\\\"'),
            (new StringField('seo_path_info', 'seoPathInfo', 750))->addFlags(new ApiAware(), new Required())->setDescription('Seo path to product. For example: \\\"Pepper-white-ground-pearl/SW10098\\\"'),
            (new BoolField('is_canonical', 'isCanonical'))->addFlags(new ApiAware())->setDescription('When set to true, search redirects to the main URL.'),
            (new BoolField('is_modified', 'isModified'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the seo url is changed.'),
            (new BoolField('is_deleted', 'isDeleted'))->addFlags(new ApiAware())->setDescription('When set to true, the URL is deleted and cannot be used any more but it is still available on table and can be restored later.'),
            (new StringField('error', 'error'))->addFlags(new Runtime(), new ApiAware()),

            (new StringField('url', 'url'))->addFlags(new ApiAware(), new Runtime()),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),

            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
