<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('fundamentals@after-sales')]
class ImportExportBeforeExportRecordEvent extends Event
{
    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $originalRecord
     */
    public function __construct(
        private readonly Config $config,
        private array $record,
        private readonly array $originalRecord,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecord(): array
    {
        return $this->record;
    }

    /**
     * @param array<string, mixed> $record
     */
    public function setRecord(array $record): void
    {
        $this->record = $record;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOriginalRecord(): array
    {
        return $this->originalRecord;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
