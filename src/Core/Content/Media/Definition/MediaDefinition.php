<?php declare(strict_types=1);

namespace Shopware\Content\Media\Definition;

use Shopware\Content\Category\Definition\CategoryDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\CatalogField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
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
use Shopware\System\Mail\Definition\MailAttachmentDefinition;
use Shopware\Content\Media\Collection\MediaBasicCollection;
use Shopware\Content\Media\Collection\MediaDetailCollection;
use Shopware\Content\Media\Event\Media\MediaDeletedEvent;
use Shopware\Content\Media\Event\Media\MediaWrittenEvent;
use Shopware\Content\Media\Repository\MediaRepository;
use Shopware\Content\Media\Struct\MediaBasicStruct;
use Shopware\Content\Media\Struct\MediaDetailStruct;
use Shopware\Content\Product\Definition\ProductManufacturerDefinition;
use Shopware\Content\Product\Definition\ProductMediaDefinition;
use Shopware\System\User\Definition\UserDefinition;

class MediaDefinition extends EntityDefinition
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
        return 'media';
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
            new CatalogField(),

            (new FkField('media_album_id', 'albumId', MediaAlbumDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(MediaAlbumDefinition::class))->setFlags(new Required()),

            new FkField('user_id', 'userId', UserDefinition::class),
            new ReferenceVersionField(UserDefinition::class),

            (new StringField('file_name', 'fileName'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('mime_type', 'mimeType'))->setFlags(new Required(), new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new IntField('file_size', 'fileSize'))->setFlags(new Required()),
            new LongTextField('meta_data', 'metaData'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new TranslatedField(new LongTextField('description', 'description')))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),

            new ManyToOneAssociationField('album', 'media_album_id', MediaAlbumDefinition::class, true),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, false),

            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'media_id', false, 'id'))->setFlags(new WriteOnly()),
            (new OneToManyAssociationField('mailAttachments', MailAttachmentDefinition::class, 'media_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'media_id', false, 'id'))->setFlags(new WriteOnly()),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'media_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new TranslationsAssociationField('translations', MediaTranslationDefinition::class, 'media_id', false, 'id'))->setFlags(new Required(), new CascadeDelete(), new WriteOnly()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return MediaRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return MediaBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return MediaDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return MediaWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return MediaBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return MediaTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return MediaDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return MediaDetailCollection::class;
    }
}
