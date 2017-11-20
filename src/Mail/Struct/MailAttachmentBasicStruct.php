<?php declare(strict_types=1);

namespace Shopware\Mail\Struct;

use Shopware\Api\Entity\Entity;

class MailAttachmentBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $mailUuid;

    /**
     * @var string
     */
    protected $mediaUuid;

    /**
     * @var string|null
     */
    protected $shopUuid;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getMailUuid(): string
    {
        return $this->mailUuid;
    }

    public function setMailUuid(string $mailUuid): void
    {
        $this->mailUuid = $mailUuid;
    }

    public function getMediaUuid(): string
    {
        return $this->mediaUuid;
    }

    public function setMediaUuid(string $mediaUuid): void
    {
        $this->mediaUuid = $mediaUuid;
    }

    public function getShopUuid(): ?string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(?string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
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
