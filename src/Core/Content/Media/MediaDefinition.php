<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\System\User\UserDefinition;

class MediaDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'media';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MediaCollection::class;
    }

    public function getEntityClass(): string
    {
        return MediaEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('user_id', 'userId', UserDefinition::class))->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new FkField('media_folder_id', 'mediaFolderId', MediaFolderDefinition::class))->addFlags(new ReadProtected(SalesChannelApiSource::class)),

            (new StringField('mime_type', 'mimeType'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE), new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new StringField('file_extension', 'fileExtension'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new DateTimeField('uploaded_at', 'uploadedAt'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new LongTextField('file_name', 'fileName'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('file_size', 'fileSize'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new BlobField('media_type', 'mediaTypeRaw'))->addFlags(new ReadProtected(AdminApiSource::class, SalesChannelApiSource::class), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new JsonField('meta_data', 'metaData'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new JsonField('media_type', 'mediaType'))->addFlags(new WriteProtected(), new Runtime(), new ReadProtected(SalesChannelApiSource::class)),
            (new TranslatedField('alt'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('title'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('url', 'url'))->addFlags(new Runtime()),
            (new BoolField('has_file', 'hasFile'))->addFlags(new Runtime()),
            new BoolField('private', 'private'),
            new TranslatedField('customFields'),

            (new BlobField('thumbnails_ro', 'thumbnailsRo'))->addFlags(new Computed(), new ReadProtected(AdminApiSource::class, SalesChannelApiSource::class)),

            (new TranslationsAssociationField(MediaTranslationDefinition::class, 'media_id'))->addFlags(new Required()),

            (new ManyToManyAssociationField('tags', TagDefinition::class, MediaTagDefinition::class, 'media_id', 'tag_id'))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('thumbnails', MediaThumbnailDefinition::class, 'media_id'))->addFlags(new CascadeDelete()),

            // reverse side of the associations, not available in sales-channel-api
            (new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false))->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'media_id', 'id'))->addFlags(new SetNullOnDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'media_id', 'id'))->addFlags(new SetNullOnDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToOneAssociationField('avatarUser', 'id', 'avatar_id', UserDefinition::class, false))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new ManyToOneAssociationField('mediaFolder', 'media_folder_id', MediaFolderDefinition::class, 'id', false))->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('propertyGroupOptions', PropertyGroupOptionDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('mailTemplateMedia', MailTemplateMediaDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('documentBaseConfigs', DocumentBaseConfigDefinition::class, 'logo_id', 'id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'media_id', 'id'))->addFlags(new SetNullOnDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('productConfiguratorSettings', ProductConfiguratorSettingDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('orderLineItems', OrderLineItemDefinition::class, 'cover_id'))->addFlags(new SetNullOnDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('cmsBlocks', CmsBlockDefinition::class, 'background_media_id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('cmsSections', CmsSectionDefinition::class, 'background_media_id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('cmsPages', CmsPageDefinition::class, 'preview_media_id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('documents', DocumentDefinition::class, 'document_media_file_id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
        ]);
    }
}
