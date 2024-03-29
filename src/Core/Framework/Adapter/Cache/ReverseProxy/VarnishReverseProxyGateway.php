<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\ReverseProxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @see https://github.com/varnish/varnish-modules/blob/master/src/vmod_xkey.vcc
 */
#[Package('core')]
class VarnishReverseProxyGateway extends AbstractReverseProxyGateway
{
    private const MAX_TAG_INVALIDATION = 50;

    /**
     * @var array<string, string>
     */
    private array $tagBuffer = [];

    /**
     * @internal
     *
     * @param string[] $hosts
     */
    public function __construct(
        private readonly array $hosts,
        private readonly int $concurrency,
        private readonly Client $client,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getDecorated(): AbstractReverseProxyGateway
    {
        throw new DecorationPatternException(self::class);
    }

    public function flush(): void
    {
        foreach (\array_chunk($this->tagBuffer, self::MAX_TAG_INVALIDATION) as $part) {
            $list = [];

            foreach ($this->hosts as $host) {
                $list[] = new Request('PURGE', $host, ['xkey' => implode(' ', $part)]);
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

            try {
                $pool->promise()->wait();
            } catch (\Throwable $e) {
                $this->logger->critical('Error while flushing varnish cache', ['error' => $e->getMessage(), 'tags' => $part]);
            }
        }

        $this->tagBuffer = [];
    }

    /**
     * @param string[] $tags
     */
    public function tag(array $tags, string $url, Response $response): void
    {
        $response->headers->set('xkey', implode(' ', $tags));
    }

    /**
     * @param array<string> $tags
     */
    public function invalidate(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->tagBuffer[$tag] = $tag;
        }

        if (\count($this->tagBuffer) >= self::MAX_TAG_INVALIDATION) {
            $this->flush();
        }
    }

    /**
     * @param array<string> $urls
     */
    public function ban(array $urls): void
    {
        $list = [];
        foreach ($urls as $url) {
            foreach ($this->hosts as $host) {
                $list[] = new Request('PURGE', $host . $url);
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

        try {
            $pool->promise()->wait();
        } catch (\Throwable $e) {
            $this->logger->critical('Error while flushing varnish cache', ['error' => $e->getMessage(), 'urls' => $urls]);
        }
    }

    public function banAll(): void
    {
        $this->ban(['/']);
    }
}
