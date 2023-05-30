<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('system-settings')]
class ImportExportAfterImportRecordEvent extends Event
{
    public function __construct(
        private readonly EntityWrittenContainerEvent $result,
        private readonly array $record,
        private readonly array $row,
        private readonly Config $config,
        private readonly Context $context
    ) {
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
