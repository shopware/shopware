<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannelDomain;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-import-type Domain from AbstractDomainLoader
 */
#[Package('framework')]
class DomainLoader extends AbstractDomainLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractDomainLoader
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, Domain>
     */
    public function load(): array
    {
        $query = new QueryBuilder($this->connection);

        $query->setTitle('SalesChannelDomain::load');
        $query->select(
            'CONCAT(TRIM(TRAILING \'/\' FROM domain.url), \'/\') `key`',
            'CONCAT(TRIM(TRAILING \'/\' FROM domain.url), \'/\') url',
            'LOWER(HEX(domain.id)) id',
            'LOWER(HEX(sales_channel.id)) salesChannelId',
            'LOWER(HEX(sales_channel.type_id)) typeId',
            'LOWER(HEX(domain.snippet_set_id)) snippetSetId',
            'LOWER(HEX(domain.currency_id)) currencyId',
            'LOWER(HEX(domain.language_id)) languageId',
            'sales_channel.maintenance maintenance',
            'sales_channel.maintenance_ip_whitelist maintenanceIpWhitelist',
            'snippet_set.iso as locale',
        );

        $query->from('sales_channel');
        $query->innerJoin('sales_channel', 'sales_channel_domain', 'domain', 'domain.sales_channel_id = sales_channel.id');
        $query->innerJoin('domain', 'snippet_set', 'snippet_set', 'snippet_set.id = domain.snippet_set_id');
        $query->andWhere('sales_channel.active');

        $this->eventDispatcher->dispatch(new SalesChannelDomainQueryEvent($query));

        /** @var array<string, Domain> $domains */
        $domains = FetchModeHelper::groupUnique($query->executeQuery()->fetchAllAssociative());

        return $domains;
    }
}
