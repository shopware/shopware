<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Structs;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class MailCreateResult
{
    protected ?string $mailTemplateTypeByteId = null;

    protected bool $mailTemplateTypeAlreadyExists = true;

    protected ?string $mailTemplateByteId = null;

    protected bool $mailTemplateAlreadyExists = true;

    protected ?string $enLanguageByteId;

    protected ?string $deLanguageByteId;

    public function getMailTemplateTypeByteId(): ?string
    {
        return $this->mailTemplateTypeByteId;
    }

    public function setMailTemplateTypeByteId(string $mailTemplateTypeByteId): void
    {
        $this->mailTemplateTypeByteId = $mailTemplateTypeByteId;
    }

    public function isMailTemplateTypeAlreadyExists(): bool
    {
        return $this->mailTemplateTypeAlreadyExists;
    }

    public function mailTemplateTypeDoesNotExist(): void
    {
        $this->mailTemplateTypeAlreadyExists = false;
    }

    public function getMailTemplateByteId(): ?string
    {
        return $this->mailTemplateByteId;
    }

    public function setMailTemplateByteId(?string $mailTemplateByteId): void
    {
        $this->mailTemplateByteId = $mailTemplateByteId;
    }

    public function isMailTemplateAlreadyExists(): bool
    {
        return $this->mailTemplateAlreadyExists;
    }

    public function mailTemplateDoesNotExist(): void
    {
        $this->mailTemplateAlreadyExists = false;
    }

    public function hasEnLanguageByteId(): bool
    {
        return $this->enLanguageByteId !== null;
    }

    public function getEnLanguageByteId(): ?string
    {
        return $this->enLanguageByteId;
    }

    public function setEnLanguageByteId(?string $enLanguageByteId): void
    {
        $this->enLanguageByteId = $enLanguageByteId;
    }

    public function hasDeLanguageByteId(): bool
    {
        return $this->deLanguageByteId !== null;
    }

    public function getDeLanguageByteId(): ?string
    {
        return $this->deLanguageByteId;
    }

    public function setDeLanguageByteId(?string $deLanguageByteId): void
    {
        $this->deLanguageByteId = $deLanguageByteId;
    }
}
