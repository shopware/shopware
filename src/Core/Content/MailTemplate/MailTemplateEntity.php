<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class MailTemplateEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string|null
     */
    protected $mailTemplateTypeId;

    /**
     * @var MailTemplateTypeEntity|null
     */
    protected $mailTemplateType;

    /**
     * @var bool
     */
    protected $systemDefault;

    /**
     * @var string|null
     */
    protected $senderName;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $subject;

    /**
     * @var string|null
     */
    protected $contentHtml;

    /**
     * @var string|null
     */
    protected $contentPlain;

    /**
     * @var MailTemplateTranslationCollection|null
     */
    protected $translations;

    /**
     * @var MailTemplateMediaCollection|null
     */
    protected $media;

    public function getMailTemplateType(): ?MailTemplateTypeEntity
    {
        return $this->mailTemplateType;
    }

    public function setMailTemplateType(MailTemplateTypeEntity $mailTemplateType): void
    {
        $this->mailTemplateType = $mailTemplateType;
    }

    public function getSystemDefault(): bool
    {
        return $this->systemDefault;
    }

    public function setSystemDefault(bool $systemDefault): void
    {
        $this->systemDefault = $systemDefault;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(string $senderName): void
    {
        $this->senderName = $senderName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): void
    {
        $this->subject = $subject;
    }

    public function getContentHtml(): ?string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(?string $contentHtml): void
    {
        $this->contentHtml = $contentHtml;
    }

    public function getContentPlain(): ?string
    {
        return $this->contentPlain;
    }

    public function setContentPlain(?string $contentPlain): void
    {
        $this->contentPlain = $contentPlain;
    }

    public function getTranslations(): ?MailTemplateTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(MailTemplateTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getMedia(): ?MailTemplateMediaCollection
    {
        return $this->media;
    }

    public function setMedia(MailTemplateMediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getMailTemplateTypeId(): ?string
    {
        return $this->mailTemplateTypeId;
    }

    public function setMailTemplateTypeId(?string $mailTemplateTypeId): void
    {
        $this->mailTemplateTypeId = $mailTemplateTypeId;
    }
}
