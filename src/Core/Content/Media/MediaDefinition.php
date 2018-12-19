<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;
use Shopware\Core\System\User\UserDefinition;

class MediaDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media';
    }

    public static function getCollectionClass(): string
    {
        return MediaCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MediaEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            new FkField('user_id', 'userId', UserDefinition::class),
            new FkField('media_folder_id', 'mediaFolderId', MediaFolderDefinition::class),

            (new StringField('mime_type', 'mimeType'))->setFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING), new WriteProtected(MediaProtectionFlags::WRITE_META_INFO)),
            (new StringField('file_extension', 'fileExtension'))->setFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING), new WriteProtected(MediaProtectionFlags::WRITE_META_INFO)),
            (new DateField('uploaded_at', 'uploadedAt'))->setFlags(new WriteProtected(MediaProtectionFlags::WRITE_META_INFO)),
            (new LongTextField('file_name', 'fileName'))->setFlags(new WriteProtected(MediaProtectionFlags::WRITE_META_INFO), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('file_size', 'fileSize'))->setFlags(new WriteProtected(MediaProtectionFlags::WRITE_META_INFO)),
            (new ObjectField('meta_data', 'metaData'))->setFlags(new WriteProtected(MediaProtectionFlags::WRITE_META_INFO)),
            (new ObjectField('media_type', 'mediaType'))->setFlags(new WriteProtected(MediaProtectionFlags::WRITE_META_INFO)),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslatedField('description'))->setFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('title'))->setFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('url', 'url'))->setFlags(new Deferred()),

            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, false),

            new OneToManyAssociationField('categories', CategoryDefinition::class, 'media_id', false, 'id'),
            new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'media_id', false, 'id'),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'media_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(MediaTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('thumbnails', MediaThumbnailDefinition::class, 'media_id', true))->setFlags(new CascadeDelete()),
            (new BoolField('has_file', 'hasFile'))->setFlags(new Deferred()),
            new ManyToOneAssociationField('mediaFolder', 'media_folder_id', MediaFolderDefinition::class, false),
        ]);
    }
}
