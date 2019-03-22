<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Theme;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTheme\SalesChannelThemeDefinition;

class ThemeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'theme';
    }

    public static function getCollectionClass(): string
    {
        return ThemeCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ThemeEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('author', 'author'))->addFlags(new Required()),
            (new JsonField('config', 'config'))->addFlags(new Required()),
            new JsonField('themeValues', 'values'),
            new CreatedAtField(),
            new UpdatedAtField(),

            new OneToOneAssociationField('salesChannelTheme', 'id', 'theme_id', SalesChannelThemeDefinition::class, false),
        ]);
    }
}
