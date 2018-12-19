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
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class ConfigurationGroupDefinition extends EntityDefinition
{
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

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            new IntField('position', 'position'),
            new BoolField('filterable', 'filterable'),
            new BoolField('comparable', 'comparable'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('options', ConfigurationGroupOptionDefinition::class, 'configuration_group_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ConfigurationGroupTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
        ]);
    }
}
