<?php declare(strict_types=1);

namespace Shopware\Media\Struct;

use Shopware\Category\Collection\CategoryBasicCollection;
use Shopware\Mail\Collection\MailAttachmentBasicCollection;
use Shopware\Media\Collection\MediaTranslationBasicCollection;
use Shopware\Product\Collection\ProductMediaBasicCollection;
use Shopware\User\Struct\UserBasicStruct;

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
     * @var ProductMediaBasicCollection
     */
    protected $productMedia;

    public function __construct()
    {
        $this->categories = new CategoryBasicCollection();

        $this->mailAttachments = new MailAttachmentBasicCollection();

        $this->translations = new MediaTranslationBasicCollection();

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

    public function getProductMedia(): ProductMediaBasicCollection
    {
        return $this->productMedia;
    }

    public function setProductMedia(ProductMediaBasicCollection $productMedia): void
    {
        $this->productMedia = $productMedia;
    }
}
