<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Response;
use function array_chunk;
use function array_map;
use function func_get_arg;
use function implode;
use function sprintf;

class FastlyReverseProxyGateway extends AbstractReverseProxyGateway
{
    private const API_URL = 'https://api.fastly.com';
    private const MAX_TAG_INVALIDATION = 256;

    protected Client $client;

    protected string $serviceId;

    protected string $apiKey;

    protected string $softPurge;

    protected int $concurrency;

    protected string $tagPrefix;

    /**
     * @internal
     */
    public function __construct(Client $client, string $serviceId, string $apiKey, string $softPurge, int $concurrency, string $tagPrefix)
    {
        $this->client = $client;
        $this->serviceId = $serviceId;
        $this->apiKey = $apiKey;
        $this->softPurge = $softPurge;
        $this->concurrency = $concurrency;
        $this->tagPrefix = $tagPrefix;
    }

    public function getDecorated(): AbstractReverseProxyGateway
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.5.0 - Parameter $response will be required
     */
    public function tag(array $tags, string $url/*, Response $response */): void
    {
        if (\func_num_args() < 3 || !func_get_arg(2) instanceof Response) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Method `tag()` in "FastlyReverseProxyGateway" expects third parameter of type `Response` in v6.5.0.0.'
            );
        }

        /** @var Response|null $response */
        $response = \func_num_args() === 3 ? func_get_arg(2) : null;

        if ($response === null) {
            throw new \InvalidArgumentException('Parameter $response is required for FastlyReverseProxyGateway');
        }

        $response->headers->set('surrogate-key', implode(' ', $this->prefixTags($tags)));
    }

    public function invalidate(array $tags): void
    {
        foreach (array_chunk($tags, self::MAX_TAG_INVALIDATION) as $part) {
            $this->client->post(sprintf('%s/service/%s/purge', self::API_URL, $this->serviceId), [
                'headers' => [
                    'Fastly-Key' => $this->apiKey,
                    'surrogate-key' => implode(' ', $this->prefixTags($part)),
                    'fastly-soft-purge' => $this->softPurge,
                ],
            ]);
        }
    }

    public function ban(array $urls): void
    {
        $list = [];

        foreach ($urls as $url) {
            $list[] = new Request('PURGE', self::API_URL . $url, [
                'Fastly-Key' => $this->apiKey,
                'fastly-soft-purge' => $this->softPurge,
            ]);
        }

        $pool = new Pool($this->client, $list, [
            'concurrency' => $this->concurrency,
            'rejected' => function (TransferException $reason): void {
                if ($reason instanceof ServerException) {
                    throw new \RuntimeException(sprintf('BAN request failed to %s failed with error: %s', $reason->getRequest()->getUri()->__toString(), $reason->getMessage()), 0, $reason);
                }

                throw $reason;
            },
        ]);

        $pool->promise()->wait();
    }

    public function banAll(): void
    {
        $this->client->post(sprintf('%s/service/%s/purge_all', self::API_URL, $this->serviceId), [
            'headers' => [
                'Fastly-Key' => $this->apiKey,
                'fastly-soft-purge' => $this->softPurge,
            ],
        ]);
    }

    private function prefixTags(array $tags): array
    {
        if ($this->tagPrefix === '') {
            return $tags;
        }

        $prefix = $this->tagPrefix;

        return array_map(static function (string $tag) use ($prefix) {
            return $prefix . $tag;
        }, $tags);
    }
}
