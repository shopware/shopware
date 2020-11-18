<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @deprecated tag:v6.4.0 - Will be removed, sales channel specific templates will be handled by business events
 */
class MailTemplateSalesChannelEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $mailTemplateId;

    /**
     * @var string|null
     */
    protected $salesChannelId;

    /**
     * @var string
     */
    protected $mailTemplateTypeId;

    /**
     * @var MailTemplateTypeEntity|null
     */
    protected $mailTemplateType;

    /**
     * @var MailTemplateSalesChannelEntity|null
     */
    protected $mailTemplate;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannel;

    public function getMailTemplateId(): string
    {
        return $this->mailTemplateId;
    }

    public function setMailTemplateId(string $mailTemplateId): void
    {
        $this->mailTemplateId = $mailTemplateId;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getMailTemplateTypeId(): string
    {
        return $this->mailTemplateTypeId;
    }

    public function setMailTemplateTypeId(string $mailTemplateTypeId): void
    {
        $this->mailTemplateTypeId = $mailTemplateTypeId;
    }

    public function getMailTemplateType(): ?MailTemplateTypeEntity
    {
        return $this->mailTemplateType;
    }

    public function setMailTemplateType(MailTemplateTypeEntity $mailTemplateType): void
    {
        $this->mailTemplateType = $mailTemplateType;
    }

    public function getMailTemplate(): ?MailTemplateSalesChannelEntity
    {
        return $this->mailTemplate;
    }

    public function setMailTemplate(MailTemplateSalesChannelEntity $mailTemplate): void
    {
        $this->mailTemplate = $mailTemplate;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
