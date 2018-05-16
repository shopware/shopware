<?php declare(strict_types=1);

namespace Shopware\System\Unit;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
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
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Content\Product\ProductDefinition;
use Shopware\System\Unit\Collection\UnitBasicCollection;
use Shopware\System\Unit\Collection\UnitDetailCollection;
use Shopware\System\Unit\Definition\UnitTranslationDefinition;
use Shopware\System\Unit\Event\UnitDeletedEvent;
use Shopware\System\Unit\Event\UnitWrittenEvent;
use Shopware\System\Unit\UnitRepository;
use Shopware\System\Unit\Struct\UnitBasicStruct;
use Shopware\System\Unit\Struct\UnitDetailStruct;

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
