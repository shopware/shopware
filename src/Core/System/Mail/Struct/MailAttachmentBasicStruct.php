<?php declare(strict_types=1);

namespace Shopware\System\Mail\Struct;

use Shopware\Api\Entity\Entity;

class MailAttachmentBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $mailId;

    /**
     * @var string
     */
    protected $mediaId;

    /**
     * @var string|null
     */
    protected $shopId;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getMailId(): string
    {
        return $this->mailId;
    }

    public function setMailId(string $mailId): void
    {
        $this->mailId = $mailId;
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getShopId(): ?string
    {
        return $this->shopId;
    }

    public function setShopId(?string $shopId): void
    {
        $this->shopId = $shopId;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
