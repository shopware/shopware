<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Symfony\Contracts\EventDispatcher\Event;

class ImportExportBeforeExportRecordEvent extends Event
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var array
     */
    private $record;

    /**
     * @var array
     */
    private $originalRecord;

    public function __construct(Config $config, array $record, array $originalRecord)
    {
        $this->config = $config;
        $this->record = $record;
        $this->originalRecord = $originalRecord;
    }

    public function getRecord(): array
    {
        return $this->record;
    }

    public function setRecord(array $record): void
    {
        $this->record = $record;
    }

    public function getOriginalRecord(): array
    {
        return $this->originalRecord;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
