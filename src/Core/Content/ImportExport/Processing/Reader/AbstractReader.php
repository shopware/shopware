<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Reader;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
abstract class AbstractReader
{
    /**
     * @param resource $resource
     */
    abstract public function read(Config $config, $resource, int $offset): iterable;

    abstract public function getOffset(): int;

    protected function getDecorated(): AbstractReader
    {
        throw new \RuntimeException('Implement getDecorated');
    }
}
