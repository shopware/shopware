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
 */
#[Package('core')]
class FastlyReverseProxyGateway extends AbstractReverseProxyGateway
{
    private const API_URL = 'https://api.fastly.com';
    private const MAX_TAG_INVALIDATION = 256;

    /**
     * @var array<string, string>
     */
    private array $tagBuffer = [];

    private readonly string $appUrl;

    /**
     * @internal
     */
    public function __construct(
        protected Client $client,
        protected string $serviceId,
        protected string $apiKey,
        protected string $softPurge,
        protected int $concurrency,
        protected string $tagPrefix,
        private readonly string $instanceTag,
        string $appUrl,
        private readonly LoggerInterface $logger
    ) {
        $this->appUrl = (string) preg_replace('/^https?:\/\//', '', $appUrl);
    }

    public function flush(): void
    {
        foreach (\array_chunk($this->tagBuffer, self::MAX_TAG_INVALIDATION) as $part) {
            try {
                $this->client->post(\sprintf('%s/service/%s/purge', self::API_URL, $this->serviceId), [
                    'headers' => [
                        'Fastly-Key' => $this->apiKey,
                        'surrogate-key' => \implode(' ', $this->prefixTags($part)),
                        'fastly-soft-purge' => $this->softPurge,
                    ],
                ]);
            } catch (\Throwable $e) {
                $this->logger->critical('Error while flushing fastly cache', ['error' => $e->getMessage(), 'tags' => $part]);
            }
        }

        $this->tagBuffer = [];
    }

    public function getDecorated(): AbstractReverseProxyGateway
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param string[] $tags
     */
    public function tag(array $tags, string $url, Response $response): void
    {
        if ($this->instanceTag !== '') {
            $tags[] = $this->instanceTag;
        }

        $response->headers->set('surrogate-key', \implode(' ', $this->prefixTags($tags)));
    }

    public function invalidate(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->tagBuffer[$tag] = $tag;
        }

        if (\count($this->tagBuffer) >= self::MAX_TAG_INVALIDATION) {
            $this->flush();
        }
    }

    public function ban(array $urls): void
    {
        $list = [];

        foreach ($urls as $url) {
            $list[] = new Request('POST', self::API_URL . '/purge/' . $this->appUrl . $url, [
                'Fastly-Key' => $this->apiKey,
                'fastly-soft-purge' => $this->softPurge,
            ]);
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
            $this->logger->critical('Error while flushing fastly cache', ['error' => $e->getMessage(), 'urls' => $urls]);
        }
    }

    public function banAll(): void
    {
        if ($this->instanceTag !== '') {
            $this->invalidate([$this->instanceTag]);

            return;
        }

        try {
            $this->client->post(\sprintf('%s/service/%s/purge_all', self::API_URL, $this->serviceId), [
                'headers' => [
                    'Fastly-Key' => $this->apiKey,
                    'fastly-soft-purge' => $this->softPurge,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->critical('Error while flushing fastly cache', ['error' => $e->getMessage(), 'urls' => ['/']]);
        }
    }

    /**
     * @param string[] $tags
     *
     * @return string[]
     */
    private function prefixTags(array $tags): array
    {
        if ($this->tagPrefix === '') {
            return $tags;
        }

        $prefix = $this->tagPrefix;

        return \array_map(static fn (string $tag) => $prefix . $tag, $tags);
    }
}
