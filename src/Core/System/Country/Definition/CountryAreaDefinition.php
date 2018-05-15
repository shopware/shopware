<?php declare(strict_types=1);

namespace Shopware\System\Country\Definition;

use Shopware\System\Country\Collection\CountryAreaBasicCollection;
use Shopware\System\Country\Collection\CountryAreaDetailCollection;
use Shopware\System\Country\Event\CountryArea\CountryAreaDeletedEvent;
use Shopware\System\Country\Event\CountryArea\CountryAreaWrittenEvent;
use Shopware\System\Country\Repository\CountryAreaRepository;
use Shopware\System\Country\Struct\CountryAreaBasicStruct;
use Shopware\System\Country\Struct\CountryAreaDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Tax\Definition\TaxAreaRuleDefinition;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new BoolField('active', 'active'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new OneToManyAssociationField('countries', CountryDefinition::class, 'country_area_id', false, 'id'),
            (new TranslationsAssociationField('translations', CountryAreaTranslationDefinition::class, 'country_area_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('taxAreaRules', TaxAreaRuleDefinition::class, 'country_area_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
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

    public static function getDeletedEventClass(): string
    {
        return CountryAreaDeletedEvent::class;
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
