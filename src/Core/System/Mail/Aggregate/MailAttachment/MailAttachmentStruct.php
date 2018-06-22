<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailAttachment;

use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Mail\MailStruct;

class MailAttachmentStruct extends Entity
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
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var MailStruct|null
     */
    protected $mail;

    /**
     * @var MediaStruct|null
     */
    protected $media;

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

    public function getMail(): ?MailStruct
    {
        return $this->mail;
    }

    public function setMail(MailStruct $mail): void
    {
        $this->mail = $mail;
    }

    public function getMedia(): ?MediaStruct
    {
        return $this->media;
    }

    public function setMedia(MediaStruct $media): void
    {
        $this->media = $media;
    }
}
