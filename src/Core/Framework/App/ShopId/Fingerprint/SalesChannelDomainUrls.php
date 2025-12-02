<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId\Fingerprint;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\ShopId\Fingerprint;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class SalesChannelDomainUrls implements Fingerprint
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
        return $this->generateHash($this->fetchSalesChannelDomainUrls());
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
     * @param list<string> $urls
     */
    private function generateHash(array $urls): string
    {
        // @phpstan-ignore-next-line shopware.hasher
        return \hash('md5', implode('', $urls));
    }
}
