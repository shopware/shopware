<?php declare(strict_types=1);

namespace Shopware\Content\Media;

use Shopware\Content\Category\CategoryDefinition;
use Shopware\Content\Media\Aggregate\MediaAlbum\MediaAlbumDefinition;
use Shopware\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\CatalogField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
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
use Shopware\System\Mail\Aggregate\MailAttachment\MailAttachmentDefinition;
use Shopware\Content\Media\Collection\MediaBasicCollection;
use Shopware\Content\Media\Collection\MediaDetailCollection;
use Shopware\Content\Media\Event\MediaDeletedEvent;
use Shopware\Content\Media\Event\MediaWrittenEvent;

use Shopware\Content\Media\Struct\MediaBasicStruct;
use Shopware\Content\Media\Struct\MediaDetailStruct;


use Shopware\System\User\UserDefinition;

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
            (new OneToManyAssociationField('productManufacturers', \Shopware\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition::class, 'media_id', false, 'id'))->setFlags(new WriteOnly()),
            (new OneToManyAssociationField('productMedia', \Shopware\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition::class, 'media_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
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
