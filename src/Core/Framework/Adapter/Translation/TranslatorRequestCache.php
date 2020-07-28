<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Translation;

class TranslatorRequestCache
{
    /**
     * @var string|null
     */
    private $snippetSetId;

    private $isCustomized = [];

    /**
     * @var string|null
     */
    private $fallbackLocale;

    public function reset(): void
    {
        $this->snippetSetId = null;
        $this->isCustomized = [];
        $this->fallbackLocale = null;
    }

    public function getSnippetSetId(): ?string
    {
        return $this->snippetSetId;
    }

    public function setSnippetSetId(?string $snippetSetId): void
    {
        $this->snippetSetId = $snippetSetId;
    }

    public function getIsCustomized(): array
    {
        return $this->isCustomized;
    }

    public function setIsCustomized(array $isCustomized): void
    {
        $this->isCustomized = $isCustomized;
    }

    public function getFallbackLocale(): ?string
    {
        return $this->fallbackLocale;
    }

    public function setFallbackLocale(?string $fallbackLocale): void
    {
        $this->fallbackLocale = $fallbackLocale;
    }
}
