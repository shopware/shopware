<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ImportExportAfterImportRecordEvent extends Event
{
    /**
     * @var EntityWrittenContainerEvent
     */
    private $result;

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

    public function __construct(
        EntityWrittenContainerEvent $result,
        array $record,
        array $row,
        Config $config,
        Context $context
    ) {
        $this->result = $result;
        $this->record = $record;
        $this->row = $row;
        $this->config = $config;
        $this->context = $context;
    }

    public function getResult(): EntityWrittenContainerEvent
    {
        return $this->result;
    }

    public function getRecord(): array
    {
        return $this->record;
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
