<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class MailTemplateMediaEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $mailTemplateId;

    /**
     * @var string|null
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $mediaId;

    /**
     * @var MediaEntity|null
     */
    protected $media;

    /**
     * @var MailTemplateEntity|null
     */
    protected $mailTemplate;

    public function getMailTemplateId(): string
    {
        return $this->mailTemplateId;
    }

    public function setMailTemplateId(string $mailTemplateId): void
    {
        $this->mailTemplateId = $mailTemplateId;
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function setLanguageId(?string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getMailTemplate(): ?MailTemplateEntity
    {
        return $this->mailTemplate;
    }

    public function setMailTemplate(MailTemplateEntity $mailTemplate): void
    {
        $this->mailTemplate = $mailTemplate;
    }
}
