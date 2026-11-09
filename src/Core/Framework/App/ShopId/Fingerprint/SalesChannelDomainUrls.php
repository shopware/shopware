<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId\Fingerprint;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\ShopId\Fingerprint;
use Shopware\Core\Framework\App\ShopId\FingerprintCustomCompare;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class SalesChannelDomainUrls implements Fingerprint, FingerprintCustomCompare
{
    final public const IDENTIFIER = 'sales_channel_domain_urls';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    /**
     * Newly added, removed or changed sales channel domains are an early indication that the shop ID should be changed.
     */
    public function getScore(): int
    {
        return 25;
    }

    public function getStamp(): string
    {
        return $this->generateSortedHash($this->fetchSalesChannelDomainUrls());
    }

    public function compare(?string $storedStamp): int
    {
        $urls = $this->fetchSalesChannelDomainUrls();

        if ($storedStamp === $this->generateSortedHash($urls)) {
            return 0;
        }

        /**
         * @deprecated tag:v6.8.0 - Stamps taken before the URLs were sorted will no longer match
         */
        if (!Feature::isActive('v6.8.0.0') && $storedStamp === $this->generateHash($urls)) {
            return 0;
        }

        return $this->getScore();
    }

    /**
     * @return list<string>
     */
    private function fetchSalesChannelDomainUrls(): array
    {
        return $this->connection
            ->fetchFirstColumn('SELECT url FROM sales_channel_domain');
    }

    /**
     * Sorted, so the stamp does not depend on the order in which the database returns the rows.
     *
     * @param list<string> $urls
     */
    private function generateSortedHash(array $urls): string
    {
        sort($urls);

        return $this->generateHash($urls);
    }

    /**
     * @param list<string> $urls
     */
    private function generateHash(array $urls): string
    {
        // @phpstan-ignore-next-line shopware.hasher
        return \hash('md5', implode('', $urls));
    }
}
