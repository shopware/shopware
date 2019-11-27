<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel\MailTemplateSalesChannelCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MailTemplateTypeEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $technicalName;

    /**
     * @var array|null
     */
    protected $availableEntities;

    /**
     * @var MailTemplateTypeTranslationCollection|null
     */
    protected $translations;

    /**
     * @var MailTemplateCollection|null
     */
    protected $mailTemplates;

    /**
     * @var array|null
     */
    protected $customFields;

    /**
     * @var MailTemplateSalesChannelCollection|null
     */
    protected $salesChannels;

    /**
     * @var \DateTimeInterface
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface
     */
    protected $updatedAt;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getTranslations(): ?MailTemplateTypeTranslationCollection
    {
        return $this->translations;
    }

    public function getAvailableEntities(): ?array
    {
        return $this->availableEntities;
    }

    public function setAvailableEntities(?array $availableEntities): void
    {
        $this->availableEntities = $availableEntities;
    }

    public function setTranslations(?MailTemplateTypeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getMailTemplates(): ?MailTemplateCollection
    {
        return $this->mailTemplates;
    }

    public function setMailTemplates(?MailTemplateCollection $mailTemplates): void
    {
        $this->mailTemplates = $mailTemplates;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getSalesChannels(): ?MailTemplateSalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(?MailTemplateSalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }
}
