<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Dto\BacktraceFrame;

#[Package('framework')]
class BacktraceCollector
{
    private const DEBUG_BACKTRACE_LIMIT = 5;

    public function getFirstFrame(callable $skipFrame): ?BacktraceFrame
    {
        foreach ($this->collectDebugBacktrace() as $frame) {
            if ($skipFrame($frame)) {
                continue;
            }

            return new BacktraceFrame(
                $frame['class'] ?? null,
                $frame['function'] ?? null,
            );
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectDebugBacktrace(): array
    {
        return debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, self::DEBUG_BACKTRACE_LIMIT);
    }
}
