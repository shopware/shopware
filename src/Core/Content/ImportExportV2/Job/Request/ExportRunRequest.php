<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Request;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final readonly class ExportRunRequest
{
    /**
     * @param list<string> $recordIds
     * @param array<string, mixed> $options
     */
    public function __construct(
        private string $profileName,
        private array $recordIds,
        private array $options = []
    ) {
    }

    public function getProfileName(): string
    {
        return $this->profileName;
    }

    /**
     * @return list<string>
     */
    public function getRecordIds(): array
    {
        return $this->recordIds;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
