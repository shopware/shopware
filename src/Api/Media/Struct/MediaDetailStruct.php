<?php declare(strict_types=1);

namespace Shopware\Api\Media\Struct;

use Shopware\Api\Category\Collection\CategoryBasicCollection;
use Shopware\Api\Mail\Collection\MailAttachmentBasicCollection;
use Shopware\Api\Media\Collection\MediaTranslationBasicCollection;
use Shopware\Api\Product\Collection\ProductManufacturerBasicCollection;
use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\User\Struct\UserBasicStruct;

class MediaDetailStruct extends MediaBasicStruct
{
    /**
     * @var UserBasicStruct|null
     */
    protected $user;

    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    /**
     * @var MailAttachmentBasicCollection
     */
    protected $mailAttachments;

    /**
     * @var MediaTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var ProductManufacturerBasicCollection
     */
    protected $productManufacturers;

    /**
     * @var ProductMediaBasicCollection
     */
    protected $productMedia;

    public function __construct()
    {
        $this->categories = new CategoryBasicCollection();

        $this->mailAttachments = new MailAttachmentBasicCollection();

        $this->translations = new MediaTranslationBasicCollection();

        $this->productManufacturers = new ProductManufacturerBasicCollection();

        $this->productMedia = new ProductMediaBasicCollection();
    }

    public function getUser(): ?UserBasicStruct
    {
        return $this->user;
    }

    public function setUser(?UserBasicStruct $user): void
    {
        $this->user = $user;
    }

    public function getCategories(): CategoryBasicCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryBasicCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getMailAttachments(): MailAttachmentBasicCollection
    {
        return $this->mailAttachments;
    }

    public function setMailAttachments(MailAttachmentBasicCollection $mailAttachments): void
    {
        $this->mailAttachments = $mailAttachments;
    }

    public function getTranslations(): MediaTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(MediaTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getProductManufacturers(): ProductManufacturerBasicCollection
    {
        return $this->productManufacturers;
    }

    public function setProductManufacturers(ProductManufacturerBasicCollection $productManufacturers): void
    {
        $this->productManufacturers = $productManufacturers;
    }

    public function getProductMedia(): ProductMediaBasicCollection
    {
        return $this->productMedia;
    }

    public function setProductMedia(ProductMediaBasicCollection $productMedia): void
    {
        $this->productMedia = $productMedia;
    }
}
