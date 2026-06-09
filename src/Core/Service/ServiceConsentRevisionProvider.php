<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Service\ServiceRegistry\Client;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @phpstan-type ServiceConsentRevision array{
 *     revision: string,
 *     links: array{
 *         feedback-url: string,
 *         docs-url: string,
 *         tos-url: string
 *     }
 * }
 * @phpstan-type ServiceConsentMetadata array{
 *     latest-revision: string,
 *     available-revisions: list<ServiceConsentRevision>
 * }
 *
 * @internal
 */
#[Package('framework')]
class ServiceConsentRevisionProvider
{
    public const DEFAULT_LOCALE = 'en-GB';

    public function __construct(
        private readonly Client $client,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getLatestRevision(): string
    {
        return $this->getMetadata(self::DEFAULT_LOCALE)['latest-revision'];
    }

    /**
     * @return ServiceConsentMetadata
     */
    public function getMetadata(string $locale): array
    {
        $normalizedLocale = trim($locale) !== '' ? trim($locale) : self::DEFAULT_LOCALE;

        /** @var ServiceConsentMetadata $metadata */
        $metadata = $this->cache->get(
            'service-consent-revisions-' . Hasher::hash($normalizedLocale),
            function (ItemInterface $item) use ($normalizedLocale): array {
                $item->expiresAfter(300);

                return $this->client->fetchConsentRevisions($normalizedLocale);
            }
        );

        return $metadata;
    }
}
