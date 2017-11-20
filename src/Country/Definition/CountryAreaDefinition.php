<?php declare(strict_types=1);

namespace Shopware\Country\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Country\Collection\CountryAreaBasicCollection;
use Shopware\Country\Collection\CountryAreaDetailCollection;
use Shopware\Country\Event\CountryArea\CountryAreaWrittenEvent;
use Shopware\Country\Repository\CountryAreaRepository;
use Shopware\Country\Struct\CountryAreaBasicStruct;
use Shopware\Country\Struct\CountryAreaDetailStruct;
use Shopware\Tax\Definition\TaxAreaRuleDefinition;

class CountryAreaDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'country_area';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new BoolField('active', 'active'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new OneToManyAssociationField('countries', CountryDefinition::class, 'country_area_uuid', false, 'uuid'),
            (new TranslationsAssociationField('translations', CountryAreaTranslationDefinition::class, 'country_area_uuid', false, 'uuid'))->setFlags(new Required()),
            new OneToManyAssociationField('taxAreaRules', TaxAreaRuleDefinition::class, 'country_area_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CountryAreaRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CountryAreaBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CountryAreaWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CountryAreaBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CountryAreaTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return CountryAreaDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CountryAreaDetailCollection::class;
    }
}
