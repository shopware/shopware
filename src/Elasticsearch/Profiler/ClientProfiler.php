<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use OpenSearch\Client;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type RequestInfo array{url: string, request: array<string, mixed>, response: array<string, mixed>, time: float, backtrace: string, client?: string}
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class ClientProfiler extends Client
{
    /**
     * @var list<RequestInfo>
     */
    private array $requests = [];

    private UriInterface $baseUri;

    public function setBaseUri(UriInterface $baseUri): void
    {
        $this->baseUri = $baseUri;
    }

    /**
     * @param array{
     *     index?: mixed,
     *     _source?: mixed,
     *     _source_excludes?: mixed,
     *     _source_includes?: mixed,
     *     allow_no_indices?: bool,
     *     allow_partial_search_results?: bool,
     *     analyze_wildcard?: bool,
     *     analyzer?: string,
     *     batched_reduce_size?: int,
     *     cancel_after_time_interval?: string,
     *     ccs_minimize_roundtrips?: bool,
     *     default_operator?: mixed,
     *     df?: string,
     *     docvalue_fields?: mixed,
     *     expand_wildcards?: mixed,
     *     explain?: bool,
     *     from?: int,
     *     ignore_throttled?: bool,
     *     ignore_unavailable?: bool,
     *     include_named_queries_score?: bool,
     *     lenient?: bool,
     *     max_concurrent_shard_requests?: int,
     *     phase_took?: bool,
     *     pre_filter_shard_size?: int,
     *     preference?: string,
     *     q?: string,
     *     request_cache?: bool,
     *     rest_total_hits_as_int?: bool,
     *     routing?: mixed,
     *     scroll?: string,
     *     search_pipeline?: string,
     *     search_type?: mixed,
     *     seq_no_primary_term?: bool,
     *     size?: int,
     *     sort?: mixed,
     *     stats?: mixed,
     *     stored_fields?: mixed,
     *     suggest_field?: string,
     *     suggest_mode?: mixed,
     *     suggest_size?: int,
     *     suggest_text?: string,
     *     terminate_after?: int,
     *     timeout?: string,
     *     track_scores?: bool,
     *     track_total_hits?: mixed,
     *     typed_keys?: bool,
     *     verbose_pipeline?: bool,
     *     version?: bool,
     *     pretty?: bool,
     *     human?: bool,
     *     error_trace?: bool,
     *     source?: string,
     *     filter_path?: mixed,
     *     body?: mixed
     * } $request Copied from parent class. Also look there for possible PHPStan issues
     *
     * @return array<string, mixed>
     */
    public function search(array $request = [])
    {
        $time = microtime(true);
        $response = parent::search($request);

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $this->requests[] = [
            'url' => $this->assembleUrl($request, '_search'),
            'request' => $request,
            'response' => $response,
            'time' => microtime(true) - $time,
            'backtrace' => \sprintf('%s:%s', $backtrace[1]['class'] ?? '', $backtrace[1]['function']),
        ];

        return $response;
    }

    /**
     * @param array{
     *     index?: mixed,
     *     allow_partial_results?: bool,
     *     ccs_minimize_roundtrips?: bool,
     *     max_concurrent_searches?: int,
     *     max_concurrent_shard_requests?: int,
     *     pre_filter_shard_size?: int,
     *     rest_total_hits_as_int?: bool,
     *     search_type?: mixed,
     *     typed_keys?: bool,
     *     pretty?: bool,
     *     human?: bool,
     *     error_trace?: bool,
     *     source?: string,
     *     filter_path?: mixed,
     *     body?: mixed
     * } $params Copied from parent class. Also look there for possible PHPStan issues
     *
     * @return array<string, mixed>
     */
    public function msearch(array $params = [])
    {
        $time = microtime(true);
        $response = parent::msearch($params);

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $this->requests[] = [
            'url' => $this->assembleUrl($params, '_msearch'),
            'request' => $params,
            'response' => $response,
            'time' => microtime(true) - $time,
            'backtrace' => \sprintf('%s:%s', $backtrace[1]['class'] ?? '', $backtrace[1]['function']),
        ];

        return $response;
    }

    public function resetRequests(): void
    {
        $this->requests = [];
    }

    /**
     * @return list<RequestInfo>
     */
    public function getCalledRequests(): array
    {
        return $this->requests;
    }

    /**
     * @param array{
     *     index?: string,
     *     _source?: mixed,
     *     _source_excludes?: mixed,
     *     _source_includes?: mixed,
     *     pipeline?: string,
     *     refresh?: mixed,
     *     require_alias?: bool,
     *     routing?: string,
     *     timeout?: string,
     *     wait_for_active_shards?: mixed,
     *     pretty?: bool,
     *     human?: bool,
     *     error_trace?: bool,
     *     source?: string,
     *     filter_path?: mixed,
     *     body?: mixed
     * } $params Copied from parent class. Also look there for possible PHPStan issues
     *
     * @return array<string, mixed>
     */
    public function bulk(array $params = [])
    {
        $time = microtime(true);
        $response = parent::bulk($params);

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $this->requests[] = [
            'url' => $this->assembleUrl($params, '_bulk'),
            'request' => $params,
            'response' => $response,
            'time' => microtime(true) - $time,
            'backtrace' => \sprintf('%s:%s', $backtrace[1]['class'] ?? '', $backtrace[1]['function']),
        ];

        return $response;
    }

    /**
     * @param array{
     *     id?: string,
     *     context?: string,
     *     cluster_manager_timeout?: string,
     *     master_timeout?: string,
     *     timeout?: string,
     *     pretty?: bool,
     *     human?: bool,
     *     error_trace?: bool,
     *     source?: string,
     *     filter_path?: mixed,
     *     body?: mixed
     * } $params Copied from parent class. Also look there for possible PHPStan issues
     *
     * @return array<string, mixed>
     */
    public function putScript(array $params = [])
    {
        $time = microtime(true);
        $response = parent::putScript($params);

        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $this->requests[] = [
            'url' => $this->assembleScriptUrl($params),
            'request' => $params,
            'response' => $response,
            'time' => microtime(true) - $time,
            'backtrace' => \sprintf('%s:%s', $backtrace[1]['class'] ?? '', $backtrace[1]['function']),
        ];

        return $response;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function assembleUrl(array $params, string $endpoint): string
    {
        $index = $params['index'] ?? null;
        unset($params['index'], $params['body']);

        $path = $this->buildPath($index, $endpoint);
        $query = $this->buildQueryString($params);

        return $this->resolveUrl($path, $query);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function assembleScriptUrl(array $params): string
    {
        $id = isset($params['id']) ? (string) $params['id'] : '';
        unset($params['id'], $params['body']);

        return $this->resolveUrl('_scripts/' . rawurlencode($id), $this->buildQueryString($params));
    }

    /**
     * @param string|array<int, string>|null $index
     */
    private function buildPath(string|array|null $index, string $endpoint): string
    {
        if ($index === null || $index === '') {
            return $endpoint;
        }

        if (\is_array($index)) {
            $index = implode(',', array_map('trim', $index));
        }

        return $index . '/' . $endpoint;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildQueryString(array $params): string
    {
        if ($params === []) {
            return '';
        }

        return http_build_query(array_map(static function (mixed $value): mixed {
            if ($value === true) {
                return 'true';
            }

            if ($value === false) {
                return 'false';
            }

            return $value;
        }, $params));
    }

    private function resolveUrl(string $path, string $query): string
    {
        $pathWithQuery = $query === '' ? $path : $path . '?' . $query;

        $uri = UriResolver::resolve($this->baseUri, new Uri($pathWithQuery));

        return (string) $uri;
    }
}
