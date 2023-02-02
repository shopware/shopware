<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class MailTemplateTypeTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $mailTemplateTypeId;

    /**
     * @var MailTemplateTypeEntity|null
     */
    protected $mailTemplateType;

    /**
     * @var string|null
     */
    protected $name;

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

    public function setMailTemplateType(?MailTemplateTypeEntity $mailTemplateType): void
    {
        $this->mailTemplateType = $mailTemplateType;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
