<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Structs;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class MailCreationState
{
    protected ?string $mailTemplateTypeByteId = null;

    protected bool $mailTemplateTypeExists = true;

    protected ?string $mailTemplateByteId = null;

    protected bool $mailTemplateExists = true;

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

    public function mailTemplateTypeExists(): bool
    {
        return $this->mailTemplateTypeExists;
    }

    public function mailTemplateTypeDoesNotExist(): void
    {
        $this->mailTemplateTypeExists = false;
    }

    public function getMailTemplateByteId(): ?string
    {
        return $this->mailTemplateByteId;
    }

    public function setMailTemplateByteId(?string $mailTemplateByteId): void
    {
        $this->mailTemplateByteId = $mailTemplateByteId;
    }

    public function mailTemplateExists(): bool
    {
        return $this->mailTemplateExists;
    }

    public function mailTemplateDoesNotExist(): void
    {
        $this->mailTemplateExists = false;
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
