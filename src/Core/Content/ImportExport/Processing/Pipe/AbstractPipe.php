<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
abstract class AbstractPipe
{
    /**
     * @param iterable<array<string, mixed>> $record
     *
     * @return iterable<array<string, mixed>>
     */
    abstract public function in(Config $config, iterable $record): iterable;

    /**
     * @param iterable<array<string, mixed>> $record
     *
     * @return iterable<array<string, mixed>>
     */
    abstract public function out(Config $config, iterable $record): iterable;
}
