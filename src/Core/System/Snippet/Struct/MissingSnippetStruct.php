<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class MissingSnippetStruct extends Struct
{
    protected string $keyPath;

    protected string $filePath;

    protected string $availableISO;

    protected string $availableTranslation;

    protected string $missingForISO;

    protected ?string $translation = null;

    public function __construct(
        string $keyPath,
        string $filePath,
        string $availableISO,
        string $availableTranslation,
        string $missingForISO,
        ?string $translation = null
    ) {
        $this->keyPath = $keyPath;
        $this->filePath = $filePath;
        $this->availableISO = $availableISO;
        $this->availableTranslation = $availableTranslation;
        $this->missingForISO = $missingForISO;
        $this->translation = $translation;
    }

    public function getKeyPath(): string
    {
        return $this->keyPath;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getAvailableISO(): string
    {
        return $this->availableISO;
    }

    public function getAvailableTranslation(): string
    {
        return $this->availableTranslation;
    }

    public function getMissingForISO(): string
    {
        return $this->missingForISO;
    }

    public function getTranslation(): ?string
    {
        return $this->translation;
    }

    public function setTranslation(string $translation): void
    {
        $this->translation = $translation;
    }
}
