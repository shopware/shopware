<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export\Summarizer;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * @internal
 */
#[Package('framework')]
class ElasticsearchSummarizer implements PanelSummarizer
{
    private const MAX_CALLS = 20;

    public function supports(DataCollectorInterface $collector): bool
    {
        return $collector->getName() === 'elasticsearch';
    }

    public function summarize(DataCollectorInterface $collector, mixed $data): array
    {
        $requests = \is_array($data) && \is_array($data['requests'] ?? null) ? array_values(array_filter($data['requests'], 'is_array')) : [];

        $calls = [];
        $totalTime = 0.0;
        foreach ($requests as $request) {
            $time = (float) ($request['time'] ?? 0);
            $totalTime += $time;

            if (\count($calls) < self::MAX_CALLS) {
                $calls[] = $this->call($request, $time);
            }
        }

        return [
            'enabled' => \is_array($data) && (bool) ($data['enabled'] ?? false),
            'requests' => \count($requests),
            'timeMs' => round($totalTime * 1000, 1),
            'calls' => $calls,
        ];
    }

    /**
     * @param array<mixed> $request
     *
     * @return array<string, mixed>
     */
    private function call(array $request, float $time): array
    {
        $response = \is_array($request['response'] ?? null) ? $request['response'] : [];
        $body = \is_array($request['request'] ?? null) ? ($request['request']['body'] ?? $request['request']) : null;
        $hits = \is_array($response['hits'] ?? null) ? $response['hits'] : null;
        $total = $hits['total'] ?? null;

        return array_filter([
            'url' => $request['url'] ?? null,
            'client' => $request['client'] ?? null,
            'timeMs' => round($time * 1000, 1),
            'caller' => $request['backtrace'] ?? null,
            'took' => $response['took'] ?? null,
            'totalHits' => \is_array($total) ? ($total['value'] ?? null) : $total,
            'returnedHits' => \is_array($hits['hits'] ?? null) ? \count($hits['hits']) : null,
            'responses' => \is_array($response['responses'] ?? null) ? \count($response['responses']) : null,
            'errors' => isset($response['errors']) || isset($response['error']) ? (bool) ($response['errors'] ?? true) : null,
            'bodyBytes' => $body === null ? null : \strlen((string) json_encode($body, \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE)),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
