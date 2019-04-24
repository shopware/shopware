<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Internal;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Tag\TagDefinition;
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

            (new StringField('mime_type', 'mimeType'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new StringField('file_extension', 'fileExtension'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new DateField('uploaded_at', 'uploadedAt'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new LongTextField('file_name', 'fileName'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('file_size', 'fileSize'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new BlobField('meta_data', 'metaDataRaw'))->addFlags(new Internal(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new BlobField('media_type', 'mediaTypeRaw'))->addFlags(new Internal(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new JsonField('meta_data', 'metaData'))->addFlags(new WriteProtected(), new Deferred()),
            (new JsonField('media_type', 'mediaType'))->addFlags(new WriteProtected(), new Deferred()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslatedField('alt'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('title'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('url', 'url'))->addFlags(new Deferred()),
            new TranslatedField('attributes'),

            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),

            new OneToManyAssociationField('categories', CategoryDefinition::class, 'media_id', 'id'),
            new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'media_id', 'id'),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToOneAssociationField('avatarUser', 'id', 'avatar_id', UserDefinition::class, false))->addFlags(new CascadeDelete()),
            (new TranslationsAssociationField(MediaTranslationDefinition::class, 'media_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('thumbnails', MediaThumbnailDefinition::class, 'media_id'))->addFlags(new CascadeDelete()),
            (new BlobField('thumbnails_ro', 'thumbnailsRo'))->addFlags(new Computed(), new Internal()),
            (new BoolField('has_file', 'hasFile'))->addFlags(new Deferred()),
            new ManyToOneAssociationField('mediaFolder', 'media_folder_id', MediaFolderDefinition::class, 'id', false),
            new OneToManyAssociationField('propertyGroupOptions', PropertyGroupOptionDefinition::class, 'media_id'),
            new ManyToManyAssociationField('tags', TagDefinition::class, MediaTagDefinition::class, 'media_id', 'tag_id'),
            (new OneToManyAssociationField('mailTemplateMedia', MailTemplateMediaDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, 'media_id'),
            (new OneToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('productConfiguratorSettings', ProductConfiguratorSettingDefinition::class, 'media_id'),
            (new OneToManyAssociationField('orderLineItems', OrderLineItemDefinition::class, 'cover_id'))->addFlags(new RestrictDelete()),
        ]);
    }
}
