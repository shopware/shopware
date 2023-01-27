<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Response;
use function sprintf;

#[Package('storefront')]
class RedisReverseProxyGateway extends AbstractReverseProxyGateway
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
     * @param \Redis|\RedisCluster $redis
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
            $this->redis->lPush($tag, $url);
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
                    throw new \RuntimeException(sprintf('BAN request failed to %s failed with error: %s', $reason->getRequest()->getUri()->__toString(), $reason->getMessage()), 0, $reason);
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
