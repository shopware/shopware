<?php declare(strict_types=1);

namespace Shopware\User\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Locale\Definition\LocaleDefinition;
use Shopware\Media\Definition\MediaDefinition;
use Shopware\User\Collection\UserBasicCollection;
use Shopware\User\Collection\UserDetailCollection;
use Shopware\User\Event\User\UserWrittenEvent;
use Shopware\User\Repository\UserRepository;
use Shopware\User\Struct\UserBasicStruct;
use Shopware\User\Struct\UserDetailStruct;

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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('locale_uuid', 'localeUuid', LocaleDefinition::class))->setFlags(new Required()),
            (new StringField('user_role_uuid', 'roleUuid'))->setFlags(new Required()),
            (new StringField('user_name', 'name'))->setFlags(new Required()),
            (new StringField('password', 'password'))->setFlags(new Required()),
            (new DateField('last_login', 'lastLogin'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new StringField('email', 'email'))->setFlags(new Required()),
            (new IntField('failed_logins', 'failedLogins'))->setFlags(new Required()),
            new StringField('encoder', 'encoder'),
            new StringField('api_key', 'apiKey'),
            new StringField('session_id', 'sessionId'),
            new BoolField('active', 'active'),
            new DateField('locked_until', 'lockedUntil'),
            new BoolField('extended_editor', 'extendedEditor'),
            new BoolField('disabled_cache', 'disabledCache'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('locale', 'locale_uuid', LocaleDefinition::class, false),
            new OneToManyAssociationField('media', MediaDefinition::class, 'user_uuid', false, 'uuid'),
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
