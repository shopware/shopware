<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryArea;

use Shopware\System\Country\Aggregate\CountryArea\Collection\CountryAreaBasicCollection;
use Shopware\System\Country\Aggregate\CountryArea\Collection\CountryAreaDetailCollection;
use Shopware\System\Country\Aggregate\CountryArea\Event\CountryAreaDeletedEvent;
use Shopware\System\Country\Aggregate\CountryArea\Event\CountryAreaWrittenEvent;
use Shopware\System\Country\Aggregate\CountryAreaTranslation\CountryAreaTranslationDefinition;
use Shopware\System\Country\CountryDefinition;

use Shopware\System\Country\Aggregate\CountryArea\Struct\CountryAreaBasicStruct;
use Shopware\System\Country\Aggregate\CountryArea\Struct\CountryAreaDetailStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleDefinition;

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
