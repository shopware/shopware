<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;

/**
 * @internal
 *
 * @phpstan-type BacktraceFrame array{function: string, line?: int, file?: string, class?: string, type?: string, args?: list<mixed>, object?: object}
 * @phpstan-type Frame array{call: string, file: ?string, line: ?int}
 */
#[Package('framework')]
class CallSiteResolver
{
    private const TRACE_DEPTH = 10;

    private const RECORDING_FUNCTION = 'record';

    private const DAL_NAMESPACES = [
        'Shopware\\Core\\Framework\\DataAbstractionLayer\\',
        'Shopware\\Core\\System\\SalesChannel\\Entity\\',
        'Shopware\\Core\\Profiling\\DataAbstractionLayer\\',
        'Shopware\\Elasticsearch\\Framework\\DataAbstractionLayer\\',
    ];

    private const REPOSITORIES = [
        EntityRepository::class,
        SalesChannelRepository::class,
    ];

    private const DECORATED_SERVICES = [
        ProfilingEntityReader::class => EntityReaderInterface::class,
        ProfilingEntitySearcher::class => EntitySearcherInterface::class,
        ProfilingEntityAggregator::class => EntityAggregatorInterface::class,
        ProfilingEntityWriter::class => EntityWriterInterface::class,
    ];

    /**
     * @param list<BacktraceFrame> $frames
     *
     * @return list<Frame>
     */
    public function resolve(array $frames): array
    {
        $entry = 0;
        $nested = false;

        foreach ($frames as $index => $frame) {
            $class = $frame['class'] ?? '';

            if ($index > 0 && $class === DalCollector::class && $frame['function'] === self::RECORDING_FUNCTION) {
                $nested = true;

                break;
            }

            if (!$this->isDalFrame($class)) {
                break;
            }

            $entry = $index;
        }

        if ($nested) {
            $entry = $this->outermostRepositoryFrame($frames, $entry) ?? $entry;
        }

        return array_map(static fn (array $frame): array => [
            'call' => isset($frame['class']) ? (self::DECORATED_SERVICES[$frame['class']] ?? $frame['class']) . '::' . $frame['function'] : $frame['function'],
            'file' => $frame['file'] ?? null,
            'line' => $frame['line'] ?? null,
        ], \array_slice($frames, $entry, self::TRACE_DEPTH));
    }

    /**
     * @param list<BacktraceFrame> $frames
     */
    private function outermostRepositoryFrame(array $frames, int $last): ?int
    {
        $found = null;

        foreach (\array_slice($frames, 0, $last + 1) as $index => $frame) {
            if (\in_array($frame['class'] ?? '', self::REPOSITORIES, true)) {
                $found = $index;
            }
        }

        return $found;
    }

    private function isDalFrame(string $class): bool
    {
        if ($class === Profiler::class || $class === Context::class) {
            return true;
        }

        foreach (self::DAL_NAMESPACES as $namespace) {
            if (str_starts_with($class, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
