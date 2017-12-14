<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Struct;

use Shopware\Api\Entity\Entity;

class MailTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $mailUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $fromMail;

    /**
     * @var string
     */
    protected $fromName;

    /**
     * @var string
     */
    protected $subject;

    /**
     * @var string
     */
    protected $content;

    /**
     * @var string
     */
    protected $contentHtml;

    public function getMailUuid(): string
    {
        return $this->mailUuid;
    }

    public function setMailUuid(string $mailUuid): void
    {
        $this->mailUuid = $mailUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
    }

    public function getFromMail(): string
    {
        return $this->fromMail;
    }

    public function setFromMail(string $fromMail): void
    {
        $this->fromMail = $fromMail;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    public function setFromName(string $fromName): void
    {
        $this->fromName = $fromName;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(string $contentHtml): void
    {
        $this->contentHtml = $contentHtml;
    }
}
