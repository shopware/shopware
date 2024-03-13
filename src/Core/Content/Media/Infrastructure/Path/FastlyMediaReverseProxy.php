<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Path;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\Core\Application\MediaReverseProxy;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class FastlyMediaReverseProxy implements MediaReverseProxy
{
    private const API_URL = 'https://api.fastly.com';

    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly string $apiKey,
        private readonly string $softPurge,
        private readonly int $concurrency,
        private readonly LoggerInterface $logger
    ) {
    }

    public function enabled(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * @param array<string> $urls
     */
    public function ban(array $urls): void
    {
        if (empty($urls)) {
            return;
        }

        $list = [];

        foreach ($urls as $url) {
            $list[] = new Request('POST', self::API_URL . '/purge/' . $url, [
                'Fastly-Key' => $this->apiKey,
                'fastly-soft-purge' => $this->softPurge,
            ]);
        }

        $pool = new Pool($this->client, $list, [
            'concurrency' => $this->concurrency,
            'rejected' => function (TransferException $reason): void {
                if ($reason instanceof ServerException) {
                    throw MediaException::cannotBanRequest($reason->getRequest()->getUri()->__toString(), $reason->getMessage(), $reason);
                }

                throw $reason;
            },
        ]);

        try {
            $pool->promise()->wait();
        } catch (\Throwable $e) {
            $this->logger->critical('Error while flushing fastly media cache', ['error' => $e->getMessage(), 'urls' => $urls]);
        }
    }
}
