<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:becomes-internal
 */
#[Package('core')]
class RedisReverseProxyGateway extends \Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway
{
    private string $keyScript = <<<LUA
local list = {}

for _, key in ipairs(ARGV) do
    local looped = redis.call('lrange', key, 0, -1)

    for _, url in ipairs(looped) do
        list[url] = true
    end
end

local final = {}

for val, _ in pairs(list) do
    table.insert(final, val);
end

return final
LUA;

    /**
     * @param string[] $hosts
     * param cannot be natively typed, as symfony might change the type in the future
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Relay\Relay $redis
     * @param array{'method': string, 'headers': array<string, string>} $singlePurge
     * @param array{'method': string, 'headers': array<string, string>, 'urls': array<string>} $entirePurge
     */
    public function __construct(
        private readonly array $hosts,
        protected array $singlePurge,
        protected array $entirePurge,
        private readonly int $concurrency,
        private $redis,
        private readonly Client $client
    ) {
    }

    /**
     * @param string[] $tags
     */
    public function tag(array $tags, string $url, Response $response): void
    {
        foreach ($tags as $tag) {
            $this->redis->lPush($tag, $url); // @phpstan-ignore-line - because multiple redis implementations phpstan doesn't like this
        }
    }

    /**
     * @param array<string> $tags
     */
    public function invalidate(array $tags): void
    {
        $urls = $this->redis->eval($this->keyScript, $tags);

        $this->ban($urls);
        $this->redis->del(...$tags);
    }

    public function ban(array $urls): void
    {
        $list = [];

        foreach ($urls as $url) {
            foreach ($this->hosts as $host) {
                $list[] = new Request($this->singlePurge['method'], $host . $url, $this->singlePurge['headers']);
            }
        }

        $pool = new Pool($this->client, $list, [
            'concurrency' => $this->concurrency,
            'rejected' => function (TransferException $reason): void {
                if ($reason instanceof ServerException) {
                    throw ReverseProxyException::cannotBanRequest($reason->getRequest()->getUri()->__toString(), $reason->getMessage(), $reason);
                }

                throw $reason;
            },
        ]);

        $pool->promise()->wait();
    }

    public function banAll(): void
    {
        $list = [];

        foreach ($this->entirePurge['urls'] as $url) {
            foreach ($this->hosts as $host) {
                $list[] = new Request($this->entirePurge['method'], $host . $url, $this->entirePurge['headers']);
            }
        }

        $pool = new Pool($this->client, $list, [
            'concurrency' => $this->concurrency,
            'rejected' => function (\Throwable $reason): void {
                if ($reason instanceof ServerException) {
                    throw ReverseProxyException::cannotBanRequest($reason->getRequest()->getUri()->__toString(), $reason->getMessage(), $reason);
                }

                throw $reason;
            },
        ]);

        $pool->promise()->wait();
    }

    public function getDecorated(): AbstractReverseProxyGateway
    {
        throw new DecorationPatternException(self::class);
    }
}
