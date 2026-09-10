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

    /**
     * Announces the records of an upcoming `out()` window, so a pipe can resolve for all of them at once what it
     * would otherwise look up per record.
     *
     * A pipe receives the records in its own input shape and returns them in its output shape, so the next pipe of a
     * chain sees what it will see during `out()`. Only side effect free transformations belong here.
     *
     * @param list<array<string, mixed>> $records
     *
     * @return list<array<string, mixed>>
     */
    public function warmUp(Config $config, array $records): array
    {
        return $records;
    }
}
