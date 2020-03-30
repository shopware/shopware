<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\Struct\Config;

/**
 * @experimental We might break this in v6.2
 */
abstract class AbstractPipe
{
    abstract public function in(Config $config, iterable $record): iterable;

    abstract public function out(Config $config, iterable $record): iterable;
}
