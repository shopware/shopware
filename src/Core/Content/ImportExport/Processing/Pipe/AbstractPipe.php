<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
abstract class AbstractPipe
{
    abstract public function in(Config $config, iterable $record): iterable;

    abstract public function out(Config $config, iterable $record): iterable;

    protected function getDecorated(): AbstractPipe
    {
        throw new \RuntimeException('Implement getDecorated');
    }
}
