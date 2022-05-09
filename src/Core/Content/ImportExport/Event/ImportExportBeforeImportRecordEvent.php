<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class ImportExportBeforeImportRecordEvent extends Event
{
    /**
     * @var array
     */
    private $record;

    /**
     * @var array
     */
    private $row;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Context
     */
    private $context;

    public function __construct(array $record, array $row, Config $config, Context $context)
    {
        $this->record = $record;
        $this->row = $row;
        $this->config = $config;
        $this->context = $context;
    }

    public function getRecord(): array
    {
        return $this->record;
    }

    public function setRecord(array $record): void
    {
        $this->record = $record;
    }

    public function getRow(): array
    {
        return $this->row;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
