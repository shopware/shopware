<?php declare(strict_types=1);

namespace Shopware\System\Mail\Struct;

use Shopware\Framework\ORM\Entity;

class MailBasicStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $orderStateId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $isHtml;

    /**
     * @var string
     */
    protected $attachment;

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

    /**
     * @var int
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $context;

    /**
     * @var bool|null
     */
    protected $dirty;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getOrderStateId(): ?string
    {
        return $this->orderStateId;
    }

    public function setOrderStateId(?string $orderStateId): void
    {
        $this->orderStateId = $orderStateId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getIsHtml(): bool
    {
        return $this->isHtml;
    }

    public function setIsHtml(bool $isHtml): void
    {
        $this->isHtml = $isHtml;
    }

    public function getAttachment(): string
    {
        return $this->attachment;
    }

    public function setAttachment(string $attachment): void
    {
        $this->attachment = $attachment;
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

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): void
    {
        $this->context = $context;
    }

    public function getDirty(): ?bool
    {
        return $this->dirty;
    }

    public function setDirty(?bool $dirty): void
    {
        $this->dirty = $dirty;
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
