<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('services-settings')]
class MissingSnippetStruct extends Struct
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $keyPath;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $filePath;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $availableISO;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $availableTranslation;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $missingForISO;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $translation;

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
