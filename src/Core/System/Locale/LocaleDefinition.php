<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Shopware\Core\System\Locale\Collection\LocaleBasicCollection;
use Shopware\Core\System\Locale\Collection\LocaleDetailCollection;
use Shopware\Core\System\Locale\Event\LocaleDeletedEvent;
use Shopware\Core\System\Locale\Event\LocaleWrittenEvent;
use Shopware\Core\System\Locale\Struct\LocaleBasicStruct;
use Shopware\Core\System\Locale\Struct\LocaleDetailStruct;
use Shopware\Core\System\Touchpoint\TouchpointDefinition;
use Shopware\Core\System\User\UserDefinition;

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
            new OneToManyAssociationField('fallbackApplications', TouchpointDefinition::class, 'fallback_locale_id', false, 'id'),
            (new OneToManyAssociationField('applications', TouchpointDefinition::class, 'locale_id', false, 'id'))->setFlags(new RestrictDelete()),
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
