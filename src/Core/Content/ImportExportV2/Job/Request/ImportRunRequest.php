<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Request;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final readonly class ImportRunRequest
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private string $profileName,
        private string $inputContents,
        private ?string $inputFileName = null,
        private ?string $inputMimeType = null,
        private array $options = []
    ) {
    }

    public function getProfileName(): string
    {
        return $this->profileName;
    }

    public function getInputContents(): string
    {
        return $this->inputContents;
    }

    public function getInputFileName(): ?string
    {
        return $this->inputFileName;
    }

    public function getInputMimeType(): ?string
    {
        return $this->inputMimeType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
