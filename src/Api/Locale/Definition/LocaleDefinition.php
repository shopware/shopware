<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Definition;

use Shopware\Api\Application\Definition\ApplicationDefinition;
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
use Shopware\Api\Locale\Collection\LocaleBasicCollection;
use Shopware\Api\Locale\Collection\LocaleDetailCollection;
use Shopware\Api\Locale\Event\Locale\LocaleDeletedEvent;
use Shopware\Api\Locale\Event\Locale\LocaleWrittenEvent;
use Shopware\Api\Locale\Repository\LocaleRepository;
use Shopware\Api\Locale\Struct\LocaleBasicStruct;
use Shopware\Api\Locale\Struct\LocaleDetailStruct;
use Shopware\Api\Shop\Definition\ShopDefinition;
use Shopware\Api\User\Definition\UserDefinition;

class LocaleDefinition extends EntityDefinition
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
        return 'locale';
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
            (new StringField('code', 'code'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new TranslatedField(new StringField('territory', 'territory')),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new OneToManyAssociationField('applications', ApplicationDefinition::class, 'fallback_locale_id', false, 'id'),
            (new OneToManyAssociationField('applications', ApplicationDefinition::class, 'locale_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new TranslationsAssociationField('translations', LocaleTranslationDefinition::class, 'locale_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('shops', ShopDefinition::class, 'locale_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('users', UserDefinition::class, 'locale_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return LocaleRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return LocaleBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return LocaleDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return LocaleWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return LocaleBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return LocaleTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return LocaleDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return LocaleDetailCollection::class;
    }
}
