<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class RedisReverseProxyGateway extends AbstractReverseProxyGateway
{
    /**
     * @var string[]
     */
    private array $hosts;

    private Client $client;

    private int $concurrency;

    private string $banMethod;

    /**
     * @var \Redis|\RedisCluster
     */
    private $redis;

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
     * @param \Redis|\RedisCluster $redis
     */
    public function __construct(array $hosts, int $concurrency, string $banMethod, $redis, Client $client)
    {
        $this->hosts = $hosts;
        $this->client = $client;
        $this->concurrency = $concurrency;
        $this->banMethod = $banMethod;
        $this->redis = $redis;
    }

    /**
     * @param string[] $tags
     */
    public function tag(array $tags, string $url): void
    {
        foreach ($tags as $tag) {
            $this->redis->lPush($tag, $url);
        }
    }

    /**
     * @param string[] $tags
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
                $list[] = new Request($this->banMethod, $host . $url);
            }
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

    public function getDecorated(): AbstractReverseProxyGateway
    {
        throw new DecorationPatternException(self::class);
    }
}
