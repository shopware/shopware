<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Reader;

use Shopware\Core\Content\ImportExport\Struct\Config;

/**
 * @experimental We might break this in v6.2
 */
abstract class AbstractReader
{
    /**
     * @param resource $resource
     */
    abstract public function read(Config $config, $resource, int $offset): iterable;

    abstract public function getOffset(): int;
}
