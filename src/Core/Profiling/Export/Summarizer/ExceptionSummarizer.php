<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export\Summarizer;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;

/**
 * @internal
 */
#[Package('framework')]
class ExceptionSummarizer implements PanelSummarizer
{
    private const MAX_FRAMES = 15;

    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector instanceof ExceptionDataCollector;
    }

    public function summarize(DataCollectorInterface $collector, mixed $data): ?array
    {
        \assert($collector instanceof ExceptionDataCollector);

        if (!$collector->hasException()) {
            return null;
        }

        $exception = $collector->getException();
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::createFromThrowable($exception);
        }

        return [
            ...$this->describe($exception),
            'status' => $exception->getStatusCode(),
            'trace' => array_map($this->frame(...), \array_slice($exception->getTrace(), 0, self::MAX_FRAMES)),
            'previous' => array_map($this->describe(...), $exception->getAllPrevious()),
        ];
    }

    /**
     * @return array{class: string, message: string, code: int|string, file: string, line: int}
     */
    private function describe(FlattenException $exception): array
    {
        return [
            'class' => $exception->getClass(),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function frame(array $frame): string
    {
        $class = \is_string($frame['class'] ?? null) ? $frame['class'] : '';
        $type = \is_string($frame['type'] ?? null) ? $frame['type'] : '';
        $function = \is_string($frame['function'] ?? null) ? $frame['function'] : '';
        $file = \is_string($frame['file'] ?? null) ? $frame['file'] : '';
        $line = \is_int($frame['line'] ?? null) ? $frame['line'] : 0;

        $location = \sprintf('%s:%d', $file, $line);

        return $function === '' ? $location : \sprintf('%s%s%s() %s', $class, $type, $function, $location);
    }
}
