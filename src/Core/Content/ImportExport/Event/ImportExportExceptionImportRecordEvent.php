<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('system-settings')]
class ImportExportExceptionImportRecordEvent extends Event
{
    public function __construct(
        private ?\Throwable $exception,
        private readonly array $record,
        private readonly array $row,
        private readonly Config $config,
        private readonly Context $context
    ) {
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    public function setException(?\Throwable $exception): void
    {
        $this->exception = $exception;
    }

    public function removeException(): void
    {
        $this->exception = null;
    }

    public function hasException(): bool
    {
        return $this->exception instanceof \Throwable;
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
