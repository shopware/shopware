<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use function sprintf;

class ReverseProxyCacheClearer implements CacheClearerInterface
{
    protected Client $client;

    private array $hosts;

    private string $method;

    private array $headers;

    private array $urls;

    private int $concurrency;

    public function __construct(Client $client, array $hosts, string $method, array $headers, array $urls, int $concurrency)
    {
        $this->hosts = $hosts;
        $this->method = $method;
        $this->headers = $headers;
        $this->urls = $urls;
        $this->client = $client;
        $this->concurrency = $concurrency;
    }

    public function clear(string $cacheDir): void
    {
        $list = [];

        foreach ($this->urls as $url) {
            foreach ($this->hosts as $host) {
                $list[] = new Request($this->method, $host . $url, $this->headers);
            }
        }

        $pool = new Pool($this->client, $list, [
            'concurrency' => $this->concurrency,
            'rejected' => function (\Throwable $reason): void {
                if ($reason instanceof ServerException) {
                    throw new \RuntimeException(sprintf('BAN request failed to %s failed with error: %s', $reason->getRequest()->getUri()->__toString(), $reason->getMessage()), 0, $reason);
                }

                throw $reason;
            },
        ]);

        $pool->promise()->wait();
    }
}
