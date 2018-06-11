<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\User\Collection\UserBasicCollection;
use Shopware\Core\System\User\Collection\UserDetailCollection;
use Shopware\Core\System\User\Event\UserDeletedEvent;
use Shopware\Core\System\User\Event\UserWrittenEvent;
use Shopware\Core\System\User\Struct\UserBasicStruct;
use Shopware\Core\System\User\Struct\UserDetailStruct;

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

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
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
            new OneToManyAssociationField('media', \Shopware\Core\Content\Media\MediaDefinition::class, 'user_id', false, 'id'),
        ]);
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
