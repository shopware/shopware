<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\ConfigurationGroupTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;

class ConfigurationGroupDefinition extends EntityDefinition
{
    public const DISPLAY_TYPE_TEXT = 'text';
    public const DISPLAY_TYPE_IMAGE = 'image';
    public const DISPLAY_TYPE_COLOR = 'color';

    public const SORTING_TYPE_NUMERIC = 'numeric';
    public const SORTING_TYPE_ALPHANUMERIC = 'alphanumeric';
    public const SORTING_TYPE_POSITION = 'position';

    public static function getEntityName(): string
    {
        return 'configuration_group';
    }

    public static function getCollectionClass(): string
    {
        return ConfigurationGroupCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ConfigurationGroupEntity::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        $defaults = parent::getDefaults($existence);

        $defaults['displayType'] = self::DISPLAY_TYPE_TEXT;
        $defaults['sortingType'] = self::SORTING_TYPE_ALPHANUMERIC;

        return $defaults;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            new TranslatedField('description'),
            new IntField('position', 'position'),
            new BoolField('filterable', 'filterable'),
            new BoolField('comparable', 'comparable'),
            (new StringField('display_type', 'displayType'))->setFlags(new Required()),
            (new StringField('sorting_type', 'sortingType'))->setFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('options', ConfigurationGroupOptionDefinition::class, 'configuration_group_id', false, 'id'))->addFlags(new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new TranslationsAssociationField(ConfigurationGroupTranslationDefinition::class, 'configuration_group_id'))->addFlags(new Required(), new CascadeDelete()),
        ]);
    }
}
