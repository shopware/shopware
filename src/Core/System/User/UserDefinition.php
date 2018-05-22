<?php declare(strict_types=1);

namespace Shopware\System\User;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\System\Locale\LocaleDefinition;
use Shopware\System\User\Collection\UserBasicCollection;
use Shopware\System\User\Collection\UserDetailCollection;
use Shopware\System\User\Event\UserDeletedEvent;
use Shopware\System\User\Event\UserWrittenEvent;
use Shopware\System\User\Struct\UserBasicStruct;
use Shopware\System\User\Struct\UserDetailStruct;

class UserDefinition extends EntityDefinition
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
        return 'user';
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
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(LocaleDefinition::class))->setFlags(new Required()),
            (new StringField('username', 'username'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('password', 'password'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('email', 'email'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new StringField('encoder', 'encoder'),
            new StringField('api_key', 'apiKey'),
            new StringField('session_id', 'sessionId'),
            new DateField('last_login', 'lastLogin'),
            new BoolField('active', 'active'),
            new IntField('failed_logins', 'failedLogins'),
            new DateField('locked_until', 'lockedUntil'),
            new BoolField('extended_editor', 'extendedEditor'),
            new BoolField('disabled_cache', 'disabledCache'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, false),
            new OneToManyAssociationField('media', \Shopware\Content\Media\MediaDefinition::class, 'user_id', false, 'id'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return UserRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return UserBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return UserDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return UserWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return UserBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return UserDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return UserDetailCollection::class;
    }
}
