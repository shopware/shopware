<?php declare(strict_types=1);

namespace Shopware\Api\Unit\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
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
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Unit\Collection\UnitBasicCollection;
use Shopware\Api\Unit\Collection\UnitDetailCollection;
use Shopware\Api\Unit\Event\Unit\UnitDeletedEvent;
use Shopware\Api\Unit\Event\Unit\UnitWrittenEvent;
use Shopware\Api\Unit\Repository\UnitRepository;
use Shopware\Api\Unit\Struct\UnitBasicStruct;
use Shopware\Api\Unit\Struct\UnitDetailStruct;

class UnitDefinition extends EntityDefinition
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
        return 'unit';
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
            (new TranslatedField(new StringField('short_code', 'shortCode')))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'unit_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new TranslationsAssociationField('translations', UnitTranslationDefinition::class, 'unit_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return UnitRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return UnitBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return UnitDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return UnitWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return UnitBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return UnitTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return UnitDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return UnitDetailCollection::class;
    }
}
