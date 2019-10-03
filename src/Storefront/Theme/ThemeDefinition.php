<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeMediaDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationDefinition;

class ThemeDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'theme';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ThemeCollection::class;
    }

    public function getEntityClass(): string
    {
        return ThemeEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('technical_name', 'technicalName'),
            (new StringField('name', 'name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('author', 'author'))->addFlags(new Required()),
            new TranslatedField('description'),
            new TranslatedField('labels'),
            new TranslatedField('helpTexts'),
            new TranslatedField('customFields'),
            new FkField('preview_media_id', 'previewMediaId', MediaDefinition::class),
            new FkField('parent_theme_id', 'parentThemeId', self::class),
            new JsonField('base_config', 'baseConfig'),
            new JsonField('config_values', 'configValues'),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),

            (new TranslationsAssociationField(ThemeTranslationDefinition::class, 'theme_id'))->addFlags(new Required()),

            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, ThemeSalesChannelDefinition::class, 'theme_id', 'sales_channel_id'),
            new ManyToManyAssociationField('media', MediaDefinition::class, ThemeMediaDefinition::class, 'theme_id', 'media_id'),
            new ManyToOneAssociationField('previewMedia', 'preview_media_id', MediaDefinition::class),
            new OneToManyAssociationField('childThemes', ThemeDefinition::class, 'parent_theme_id'),
        ]);
    }
}
