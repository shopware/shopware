<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Strategy\Import;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\ImportResult;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('services-settings')]
interface ImportStrategyService
{
    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $row
     */
    public function import(
        array $record,
        array $row,
        Config $config,
        Progress $progress,
        Context $context,
    ): ImportResult;

    public function commit(Config $config, Progress $progress, Context $context): ImportResult;
}
