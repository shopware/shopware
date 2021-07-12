<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal (flag:FEATURE_NEXT_8097)
 */
class ImportExportBeforeImportRowEvent extends Event
{
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

    public function __construct(array $row, Config $config, Context $context)
    {
        $this->row = $row;
        $this->config = $config;
        $this->context = $context;
    }

    public function getRow(): array
    {
        return $this->row;
    }

    public function setRow(array $row): void
    {
        $this->row = $row;
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
