<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class RoundRobinHostHttpClient implements ClientInterface, LastRequestAwareHttpClientInterface
{
    /**
     * @var non-empty-list<UriInterface>
     */
    private readonly array $hosts;

    private int $hostIndex = 0;

    private ?UriInterface $lastRequestUri = null;

    /**
     * @param non-empty-list<string|UriInterface> $hosts
     */
    public function __construct(
        private readonly ClientInterface $client,
        array $hosts
    ) {
        $this->hosts = array_map(
            static fn (string|UriInterface $host): UriInterface => $host instanceof UriInterface ? $host : new Uri($host),
            $hosts
        );
    }

    /**
     * @return non-empty-list<UriInterface>
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function getLastRequestUri(): ?UriInterface
    {
        return $this->lastRequestUri;
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri();
        if ($uri->getHost() === '') {
            $uri = UriResolver::resolve($this->getCurrentHost(), $uri);
        }

        $this->lastRequestUri = $uri;

        return $this->client->sendRequest($request->withUri($uri));
    }

    private function getCurrentHost(): UriInterface
    {
        $host = $this->hosts[$this->hostIndex];

        $this->hostIndex = ($this->hostIndex + 1) % \count($this->hosts);

        return new Uri((string) $host);
    }
}
