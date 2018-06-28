<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Catalog\ORM\CatalogField;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\MediaAlbumDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
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
use Shopware\Core\System\Mail\Aggregate\MailAttachment\MailAttachmentDefinition;
use Shopware\Core\System\User\UserDefinition;

class MediaDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CatalogField(),

            (new FkField('media_album_id', 'albumId', MediaAlbumDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(MediaAlbumDefinition::class))->setFlags(new Required()),

            new FkField('user_id', 'userId', UserDefinition::class),

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

            new OneToManyAssociationField('categories', CategoryDefinition::class, 'media_id', false, 'id'),
            (new OneToManyAssociationField('mailAttachments', MailAttachmentDefinition::class, 'media_id', false, 'id'))->setFlags(new RestrictDelete()),
            new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'media_id', false, 'id'),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'media_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', MediaTranslationDefinition::class, 'media_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return MediaCollection::class;
    }

    public static function getStructClass(): string
    {
        return MediaStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return MediaTranslationDefinition::class;
    }
}
