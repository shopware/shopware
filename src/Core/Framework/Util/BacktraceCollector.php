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
     *     line?: int,
     *     file?: string,
     *     class?: class-string,
     *     type?: '->'|'::',
     * }>
     */
    public function collect(int $limit = self::DEFAULT_LIMIT): array
    {
        return debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
    }
}
