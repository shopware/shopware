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

    /**
     * @var list<string>
     */
    protected array $englishLanguageByteIds = [];

    /**
     * @var list<string>
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
     * @return list<string>
     */
    public function getEnglishLanguageByteIds(): array
    {
        return $this->englishLanguageByteIds;
    }

    /**
     * @param list<string> $englishLanguageByteIds
     */
    public function setEnglishLanguageByteIds(array $englishLanguageByteIds): void
    {
        $this->englishLanguageByteIds = $this->uniqueLanguageByteIds($englishLanguageByteIds);
    }

    /**
     * @return list<string>
     */
    public function getGermanLanguageByteIds(): array
    {
        return $this->germanLanguageByteIds;
    }

    /**
     * @param list<string> $germanLanguageByteIds
     */
    public function setGermanLanguageByteIds(array $germanLanguageByteIds): void
    {
        $this->germanLanguageByteIds = $this->uniqueLanguageByteIds($germanLanguageByteIds);
    }

    /**
     * @param list<string> $languageByteIds
     *
     * @return list<string>
     */
    private function uniqueLanguageByteIds(array $languageByteIds): array
    {
        return array_values(array_unique($languageByteIds));
    }
}
