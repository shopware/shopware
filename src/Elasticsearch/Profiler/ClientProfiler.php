<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Profiler;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use OpenSearch\Client;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-internal - will be considered internal from 6.8.0.0 onwards
 *
 * @phpstan-type RequestInfo array{url: string, request: array<string, mixed>, response: array<string, mixed>, time: float, backtrace: string, client?: string}
 */
#[Package('framework')]
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
     * @param array<string, mixed> $request
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
     * @param array<string, mixed> $params
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
     * @param array<string, mixed> $params
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
     * @param array<string, mixed> $params
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
