<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class ImportExportExceptionImportRecordEvent extends Event
{
    /**
     * @var ?\Throwable
     */
    private $exception;

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

    public function __construct(\Throwable $exception, array $record, array $row, Config $config, Context $context)
    {
        $this->exception = $exception;
        $this->record = $record;
        $this->row = $row;
        $this->config = $config;
        $this->context = $context;
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
