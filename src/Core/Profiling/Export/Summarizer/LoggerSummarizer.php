<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export\Summarizer;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @internal
 */
#[Package('framework')]
class LoggerSummarizer implements PanelSummarizer
{
    private const WARNING = 300;

    private const MAX_ENTRIES = 30;

    private const MAX_DEPRECATIONS = 10;

    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof LoggerDataCollector;
    }

    public function summarize(DataCollectorInterface $collector, mixed $data): array
    {
        \assert($collector instanceof LoggerDataCollector);

        $entries = [];
        $deprecations = [];

        foreach ($collector->getProcessedLogs() as $log) {
            $message = $log['message'] instanceof Data ? $log['message']->getValue() : $log['message'];
            $message = \is_scalar($message) ? (string) $message : '';
            $count = (int) $log['errorCount'];

            if ($log['type'] === 'deprecation') {
                $deprecations[$message] = ($deprecations[$message] ?? 0) + $count;

                continue;
            }

            if ($log['priority'] < self::WARNING) {
                continue;
            }

            $key = $log['priorityName'] . "\0" . $log['channel'] . "\0" . $message;
            $entries[$key] ??= ['level' => $log['priorityName'], 'channel' => $log['channel'], 'message' => $message, 'count' => 0];
            $entries[$key]['count'] += $count;
        }

        arsort($deprecations);

        return [
            'errors' => $collector->countErrors(),
            'warnings' => $collector->countWarnings(),
            'deprecations' => $collector->countDeprecations(),
            'silenced' => $collector->countScreams(),
            'entries' => \array_slice(array_values($entries), 0, self::MAX_ENTRIES),
            'topDeprecations' => array_map(
                static fn (int|string $message, int $count): array => ['message' => (string) $message, 'count' => $count],
                array_keys(\array_slice($deprecations, 0, self::MAX_DEPRECATIONS, true)),
                \array_slice($deprecations, 0, self::MAX_DEPRECATIONS, true),
            ),
        ];
    }
}
