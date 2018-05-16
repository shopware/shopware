<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailTranslation\Struct;

use Shopware\Framework\ORM\Entity;

class MailTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $mailId;

    /**
     * @var string
     */
    protected $languageId;

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

    public function getMailId(): string
    {
        return $this->mailId;
    }

    public function setMailId(string $mailId): void
    {
        $this->mailId = $mailId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
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
