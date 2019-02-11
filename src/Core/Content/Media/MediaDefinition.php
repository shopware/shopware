<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ComputedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;
use Shopware\Core\Framework\SourceContext;
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
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            new FkField('user_id', 'userId', UserDefinition::class),
            new FkField('media_folder_id', 'mediaFolderId', MediaFolderDefinition::class),

            (new StringField('mime_type', 'mimeType'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING), new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new StringField('file_extension', 'fileExtension'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING), new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new DateField('uploaded_at', 'uploadedAt'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new LongTextField('file_name', 'fileName'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('file_size', 'fileSize'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new ComputedField('meta_data', 'metaDataRaw'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new ComputedField('media_type', 'mediaTypeRaw'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new JsonField('meta_data', 'metaData'))->addFlags(new WriteProtected(), new Deferred()),
            (new JsonField('media_type', 'mediaType'))->addFlags(new WriteProtected(), new Deferred()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslatedField('alt'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('title'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('url', 'url'))->addFlags(new Deferred()),
            new TranslatedField('attributes'),

            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, false),

            new OneToManyAssociationField('categories', CategoryDefinition::class, 'media_id', false, 'id'),
            new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'media_id', false, 'id'),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'media_id', false, 'id'))->addFlags(new CascadeDelete()),
            (new TranslationsAssociationField(MediaTranslationDefinition::class, 'media_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('thumbnails', MediaThumbnailDefinition::class, 'media_id', true))->addFlags(new CascadeDelete()),
            (new BoolField('has_file', 'hasFile'))->addFlags(new Deferred()),
            new ManyToOneAssociationField('mediaFolder', 'media_folder_id', MediaFolderDefinition::class, false),
            new OneToManyAssociationField('configurationGroupOptions', ConfigurationGroupOptionDefinition::class, 'media_id', false),
        ]);
    }
}
