<?php declare(strict_types=1);

namespace Shopware\Api\User\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\System\Locale\Definition\LocaleDefinition;
use Shopware\Api\Media\Definition\MediaDefinition;
use Shopware\Api\User\Collection\UserBasicCollection;
use Shopware\Api\User\Collection\UserDetailCollection;
use Shopware\Api\User\Event\User\UserDeletedEvent;
use Shopware\Api\User\Event\User\UserWrittenEvent;
use Shopware\Api\User\Repository\UserRepository;
use Shopware\Api\User\Struct\UserBasicStruct;
use Shopware\Api\User\Struct\UserDetailStruct;

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
            new OneToManyAssociationField('media', MediaDefinition::class, 'user_id', false, 'id'),
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
