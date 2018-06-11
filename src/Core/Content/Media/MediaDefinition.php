<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\MediaAlbumDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Media\Collection\MediaBasicCollection;
use Shopware\Core\Content\Media\Collection\MediaDetailCollection;
use Shopware\Core\Content\Media\Event\MediaDeletedEvent;
use Shopware\Core\Content\Media\Event\MediaWrittenEvent;
use Shopware\Core\Content\Media\Struct\MediaBasicStruct;
use Shopware\Core\Content\Media\Struct\MediaDetailStruct;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\LongTextField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
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
use Shopware\Core\System\Mail\Aggregate\MailAttachment\MailAttachmentDefinition;
use Shopware\Core\System\User\UserDefinition;

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
            (new OneToManyAssociationField('productManufacturers', \Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition::class, 'media_id', false, 'id'))->setFlags(new WriteOnly()),
            (new OneToManyAssociationField('productMedia', \Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition::class, 'media_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
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
