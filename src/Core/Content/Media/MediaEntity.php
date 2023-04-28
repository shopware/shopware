<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;

#[Package('content')]
class MediaEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string|null
     */
    protected $userId;

    /**
     * @var string|null
     */
    protected $mimeType;

    /**
     * @var string|null
     */
    protected $fileExtension;

    /**
     * @var int|null
     */
    protected $fileSize;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string|null
     */
    protected $metaDataRaw;

    /**
     * @internal
     *
     * @var string|null
     */
    protected $mediaTypeRaw;

    /**
     * @var array<string, mixed>|null
     */
    protected $metaData;

    /**
     * @var MediaType|null
     */
    protected $mediaType;

    /**
     * @var \DateTimeInterface|null
     */
    protected $uploadedAt;

    /**
     * @var string|null
     */
    protected $alt;

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var string|null
     */
    protected $fileName;

    /**
     * @var UserEntity|null
     */
    protected $user;

    /**
     * @var MediaTranslationCollection|null
     */
    protected $translations;

    /**
     * @var CategoryCollection|null
     */
    protected $categories;

    /**
     * @var ProductManufacturerCollection|null
     */
    protected $productManufacturers;

    /**
     * @var ProductMediaCollection|null
     */
    protected $productMedia;

    /**
     * @var UserCollection|null
     */
    protected $avatarUsers;

    /**
     * @var MediaThumbnailCollection|null
     */
    protected $thumbnails;

    /**
     * @var string|null
     */
    protected $mediaFolderId;

    /**
     * @var MediaFolderEntity|null
     */
    protected $mediaFolder;

    /**
     * @var bool
     */
    protected $hasFile = false;

    /**
     * @var bool
     */
    protected $private = false;

    /**
     * @var PropertyGroupOptionCollection|null
     */
    protected $propertyGroupOptions;

    /**
     * @var MailTemplateMediaCollection|null
     */
    protected $mailTemplateMedia;

    /**
     * @var TagCollection|null
     */
    protected $tags;

    /**
     * @internal
     *
     * @var string|null
     */
    protected $thumbnailsRo;

    /**
     * @var DocumentBaseConfigCollection|null
     */
    protected $documentBaseConfigs;

    /**
     * @var ShippingMethodCollection|null
     */
    protected $shippingMethods;

    /**
     * @var PaymentMethodCollection|null
     */
    protected $paymentMethods;

    /**
     * @var ProductConfiguratorSettingCollection|null
     */
    protected $productConfiguratorSettings;

    /**
     * @var OrderLineItemCollection|null
     */
    protected $orderLineItems;

    /**
     * @var CmsBlockCollection|null
     */
    protected $cmsBlocks;

    /**
     * @var CmsSectionCollection|null
     */
    protected $cmsSections;

    /**
     * @var CmsBlockCollection|null
     */
    protected $cmsPages;

    /**
     * @var DocumentCollection|null
     */
    protected $documents;

    /**
     * @var AppPaymentMethodCollection|null
     */
    protected $appPaymentMethods;

    protected ?ProductDownloadCollection $productDownloads = null;

    protected ?OrderLineItemDownloadCollection $orderLineItemDownloads = null;

    public function get(string $property)
    {
        if ($property === 'hasFile') {
            return $this->hasFile();
        }

        return parent::get($property);
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getFileExtension(): ?string
    {
        return $this->fileExtension;
    }

    public function setFileExtension(string $fileExtension): void
    {
        $this->fileExtension = $fileExtension;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetaData(): ?array
    {
        return $this->metaData;
    }

    /**
     * @param array<string, mixed> $metaData
     */
    public function setMetaData(array $metaData): void
    {
        $this->metaData = $metaData;
    }

    public function getMediaType(): ?MediaType
    {
        return $this->mediaType;
    }

    public function setMediaType(MediaType $mediaType): void
    {
        $this->mediaType = $mediaType;
    }

    public function getUploadedAt(): ?\DateTimeInterface
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeInterface $uploadedAt): void
    {
        $this->uploadedAt = $uploadedAt;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(string $alt): void
    {
        $this->alt = $alt;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): void
    {
        $this->user = $user;
    }

    public function getTranslations(): ?MediaTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(MediaTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCategories(): ?CategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getProductManufacturers(): ?ProductManufacturerCollection
    {
        return $this->productManufacturers;
    }

    public function setProductManufacturers(ProductManufacturerCollection $productManufacturers): void
    {
        $this->productManufacturers = $productManufacturers;
    }

    public function getProductMedia(): ?ProductMediaCollection
    {
        return $this->productMedia;
    }

    public function setProductMedia(ProductMediaCollection $productMedia): void
    {
        $this->productMedia = $productMedia;
    }

    public function getAvatarUsers(): ?UserCollection
    {
        return $this->avatarUsers;
    }

    public function setAvatarUsers(UserCollection $avatarUsers): void
    {
        $this->avatarUsers = $avatarUsers;
    }

    public function getThumbnails(): ?MediaThumbnailCollection
    {
        return $this->thumbnails;
    }

    public function setThumbnails(MediaThumbnailCollection $thumbnailCollection): void
    {
        $this->thumbnails = $thumbnailCollection;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function hasFile(): bool
    {
        $hasFile = $this->mimeType !== null && $this->fileExtension !== null && $this->fileName !== null;

        return $this->hasFile = $hasFile;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function getFileNameIncludingExtension(): ?string
    {
        if ($this->fileName === null || $this->fileExtension === null) {
            return null;
        }

        return sprintf('%s.%s', $this->fileName, $this->fileExtension);
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getMediaFolderId(): ?string
    {
        return $this->mediaFolderId;
    }

    public function setMediaFolderId(string $mediaFolderId): void
    {
        $this->mediaFolderId = $mediaFolderId;
    }

    public function getMediaFolder(): ?MediaFolderEntity
    {
        return $this->mediaFolder;
    }

    public function setMediaFolder(MediaFolderEntity $mediaFolder): void
    {
        $this->mediaFolder = $mediaFolder;
    }

    public function getPropertyGroupOptions(): ?PropertyGroupOptionCollection
    {
        return $this->propertyGroupOptions;
    }

    public function setPropertyGroupOptions(PropertyGroupOptionCollection $propertyGroupOptions): void
    {
        $this->propertyGroupOptions = $propertyGroupOptions;
    }

    public function getMetaDataRaw(): ?string
    {
        return $this->metaDataRaw;
    }

    public function setMetaDataRaw(string $metaDataRaw): void
    {
        $this->metaDataRaw = $metaDataRaw;
    }

    /**
     * @internal
     */
    public function getMediaTypeRaw(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('mediaTypeRaw');

        return $this->mediaTypeRaw;
    }

    /**
     * @internal
     */
    public function setMediaTypeRaw(string $mediaTypeRaw): void
    {
        $this->mediaTypeRaw = $mediaTypeRaw;
    }

    public function getMailTemplateMedia(): ?MailTemplateMediaCollection
    {
        return $this->mailTemplateMedia;
    }

    public function setMailTemplateMedia(MailTemplateMediaCollection $mailTemplateMedia): void
    {
        $this->mailTemplateMedia = $mailTemplateMedia;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @internal
     */
    public function getThumbnailsRo(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('thumbnailsRo');

        return $this->thumbnailsRo;
    }

    /**
     * @internal
     */
    public function setThumbnailsRo(string $thumbnailsRo): void
    {
        $this->thumbnailsRo = $thumbnailsRo;
    }

    public function getDocumentBaseConfigs(): ?DocumentBaseConfigCollection
    {
        return $this->documentBaseConfigs;
    }

    public function setDocumentBaseConfigs(DocumentBaseConfigCollection $documentBaseConfigs): void
    {
        $this->documentBaseConfigs = $documentBaseConfigs;
    }

    public function getShippingMethods(): ?ShippingMethodCollection
    {
        return $this->shippingMethods;
    }

    public function setShippingMethods(ShippingMethodCollection $shippingMethods): void
    {
        $this->shippingMethods = $shippingMethods;
    }

    public function getPaymentMethods(): ?PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['metaDataRaw'], $data['mediaTypeRaw']);

        return $data;
    }

    public function getProductConfiguratorSettings(): ?ProductConfiguratorSettingCollection
    {
        return $this->productConfiguratorSettings;
    }

    public function setProductConfiguratorSettings(ProductConfiguratorSettingCollection $productConfiguratorSettings): void
    {
        $this->productConfiguratorSettings = $productConfiguratorSettings;
    }

    public function getOrderLineItems(): ?OrderLineItemCollection
    {
        return $this->orderLineItems;
    }

    public function setOrderLineItems(OrderLineItemCollection $orderLineItems): void
    {
        $this->orderLineItems = $orderLineItems;
    }

    public function getCmsBlocks(): ?CmsBlockCollection
    {
        return $this->cmsBlocks;
    }

    public function setCmsBlocks(CmsBlockCollection $cmsBlocks): void
    {
        $this->cmsBlocks = $cmsBlocks;
    }

    public function getCmsSections(): ?CmsSectionCollection
    {
        return $this->cmsSections;
    }

    public function setCmsSections(CmsSectionCollection $cmsSections): void
    {
        $this->cmsSections = $cmsSections;
    }

    public function getCmsPages(): ?CmsBlockCollection
    {
        return $this->cmsPages;
    }

    public function setCmsPages(CmsBlockCollection $cmsPages): void
    {
        $this->cmsPages = $cmsPages;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): void
    {
        $this->private = $private;
    }

    public function getDocuments(): ?DocumentCollection
    {
        return $this->documents;
    }

    public function setDocuments(DocumentCollection $documents): void
    {
        $this->documents = $documents;
    }

    public function getAppPaymentMethods(): ?AppPaymentMethodCollection
    {
        return $this->appPaymentMethods;
    }

    public function setAppPaymentMethods(AppPaymentMethodCollection $appPaymentMethods): void
    {
        $this->appPaymentMethods = $appPaymentMethods;
    }

    public function getProductDownloads(): ?ProductDownloadCollection
    {
        return $this->productDownloads;
    }

    public function setProductDownloads(ProductDownloadCollection $productDownloads): void
    {
        $this->productDownloads = $productDownloads;
    }

    public function getOrderLineItemDownloads(): ?OrderLineItemDownloadCollection
    {
        return $this->orderLineItemDownloads;
    }

    public function setOrderLineItemDownloads(OrderLineItemDownloadCollection $orderLineItemDownloads): void
    {
        $this->orderLineItemDownloads = $orderLineItemDownloads;
    }
}
