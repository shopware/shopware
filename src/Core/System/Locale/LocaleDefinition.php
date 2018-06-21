<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\ORM\EntityDefinition;

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

use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Shopware\Core\System\Touchpoint\TouchpointDefinition;
use Shopware\Core\System\User\UserDefinition;

class LocaleDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'locale';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
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
            (new OneToManyAssociationField('users', UserDefinition::class, 'locale_id', false, 'id'))->setFlags(new RestrictDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return LocaleCollection::class;
    }

    public static function getStructClass(): string
    {
        return LocaleStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return LocaleTranslationDefinition::class;
    }
}
