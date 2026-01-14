<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class BacktraceCollector
{
    private const DEFAULT_LIMIT = 5;

    /**
     * @return list<array{
     *     function: string,
     *     file?: string,
     *     line?: int,
     *     class?: class-string,
     *     type?: '->'|'::',
     *     args?: list<mixed>,
     *     object?: object
     * }>
     */
    public function collect(int $limit = self::DEFAULT_LIMIT): array
    {
        return debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
    }
}
