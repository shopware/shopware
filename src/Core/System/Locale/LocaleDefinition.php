<?php declare(strict_types=1);

namespace Shopware\System\Locale;

use Shopware\Application\Application\Definition\ApplicationDefinition;
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
use Shopware\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Shopware\System\Locale\Collection\LocaleBasicCollection;
use Shopware\System\Locale\Collection\LocaleDetailCollection;
use Shopware\System\Locale\Event\LocaleDeletedEvent;
use Shopware\System\Locale\Event\LocaleWrittenEvent;
use Shopware\System\Locale\LocaleRepository;
use Shopware\System\Locale\Struct\LocaleBasicStruct;
use Shopware\System\Locale\Struct\LocaleDetailStruct;
use Shopware\System\User\UserDefinition;

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
            new OneToManyAssociationField('fallbackApplications', ApplicationDefinition::class, 'fallback_locale_id', false, 'id'),
            (new OneToManyAssociationField('applications', ApplicationDefinition::class, 'locale_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new TranslationsAssociationField('translations', LocaleTranslationDefinition::class, 'locale_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
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
