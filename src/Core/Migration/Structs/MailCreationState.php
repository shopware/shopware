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
    protected array $enLanguageByteIds = [];

    /**
     * @var list<string>
     */
    protected array $deLanguageByteIds = [];

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
    public function getEnLanguageByteIds(): array
    {
        return $this->enLanguageByteIds;
    }

    /**
     * @param list<string> $enLanguageByteIds
     */
    public function setEnLanguageByteIds(array $enLanguageByteIds): void
    {
        $this->enLanguageByteIds = $this->uniqueLanguageByteIds($enLanguageByteIds);
    }

    /**
     * @return list<string>
     */
    public function getDeLanguageByteIds(): array
    {
        return $this->deLanguageByteIds;
    }

    /**
     * @param list<string> $deLanguageByteIds
     */
    public function setDeLanguageByteIds(array $deLanguageByteIds): void
    {
        $this->deLanguageByteIds = $this->uniqueLanguageByteIds($deLanguageByteIds);
    }

    /**
     * @param list<string> $languageByteIds
     *
     * @return list<string>
     */
    private function uniqueLanguageByteIds(array $languageByteIds): array
    {
        $uniqueLanguageByteIds = [];

        foreach ($languageByteIds as $languageByteId) {
            if (\in_array($languageByteId, $uniqueLanguageByteIds, true)) {
                continue;
            }

            $uniqueLanguageByteIds[] = $languageByteId;
        }

        return $uniqueLanguageByteIds;
    }
}
