<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Structs;

use Shopware\Core\Framework\Feature;
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

    /**
     * @var array<string>
     */
    protected array $englishLanguageByteIds = [];

    /**
     * @var array<string>
     */
    protected array $germanLanguageByteIds = [];

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

    /**
     * @return array<string>
     */
    public function getEnglishLanguageByteIds(): array
    {
        return $this->englishLanguageByteIds;
    }

    /**
     * @param array<string> $englishLanguageByteIds
     */
    public function setEnglishLanguageByteIds(array $englishLanguageByteIds): void
    {
        $this->englishLanguageByteIds = $englishLanguageByteIds;
    }

    /**
     * @return array<string>
     */
    public function getGermanLanguageByteIds(): array
    {
        return $this->germanLanguageByteIds;
    }

    /**
     * @param array<string> $germanLanguageByteIds
     */
    public function setGermanLanguageByteIds(array $germanLanguageByteIds): void
    {
        $this->germanLanguageByteIds = $germanLanguageByteIds;
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed without replacement
     */
    public function hasEnLanguageByteId(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'hasEnLanguageByteId() is deprecated.');

        return $this->englishLanguageByteIds !== [];
    }

    /**
     * @deprecated tag:v6.8.0 - Use getEnglishLanguageByteIds() instead.
     */
    public function getEnLanguageByteId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'getEnLanguageByteId() is deprecated. Use getEnglishLanguageByteIds() instead.');

        return $this->englishLanguageByteIds[0] ?? null;
    }

    /**
     * @deprecated tag:v6.8.0 - Use setEnglishLanguageByteIds() instead.
     */
    public function setEnLanguageByteId(?string $enLanguageByteId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'setEnLanguageByteId() is deprecated. Use setEnglishLanguageByteIds() instead.');
        $this->englishLanguageByteIds = $enLanguageByteId !== null ? [$enLanguageByteId] : [];
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed without replacement
     */
    public function hasDeLanguageByteId(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'hasDeLanguageByteId() is deprecated.');

        return $this->germanLanguageByteIds !== [];
    }

    /**
     * @deprecated tag:v6.8.0 - Use getGermanLanguageByteIds() instead.
     */
    public function getDeLanguageByteId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'getDeLanguageByteId() is deprecated. Use getGermanLanguageByteIds() instead.');

        return $this->germanLanguageByteIds[0] ?? null;
    }

    /**
     * @deprecated tag:v6.8.0 - Use setGermanLanguageByteIds() instead.
     */
    public function setDeLanguageByteId(?string $deLanguageByteId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'setDeLanguageByteId() is deprecated. Use setGermanLanguageByteIds() instead.');
        $this->germanLanguageByteIds = $deLanguageByteId !== null ? [$deLanguageByteId] : [];
    }
}
