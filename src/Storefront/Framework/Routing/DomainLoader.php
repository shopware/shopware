<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Storefront\Framework\Routing\Struct\DomainCollection;

/**
 * @phpstan-import-type Domain from AbstractDomainLoader
 */
#[Package('framework')]
class DomainLoader extends AbstractDomainLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractDomainLoader
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed, use loadDomains() instead
     *
     * @return array<string, Domain>
     */
    public function load(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'loadDomains()')
        );

        return $this->fetch();
    }

    public function loadDomains(): DomainCollection
    {
        return DomainCollection::fromArray($this->fetch());
    }

    /**
     * @return array<string, Domain>
     */
    private function fetch(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select(
            'CONCAT(TRIM(TRAILING \'/\' FROM domain.url), \'/\') `key`',
            'CONCAT(TRIM(TRAILING \'/\' FROM domain.url), \'/\') url',
            'LOWER(HEX(domain.id)) id',
            'LOWER(HEX(sales_channel.id)) salesChannelId',
            'LOWER(HEX(sales_channel.type_id)) typeId',
            'LOWER(HEX(domain.snippet_set_id)) snippetSetId',
            'LOWER(HEX(domain.currency_id)) currencyId',
            'LOWER(HEX(domain.language_id)) languageId',
            'LOWER(HEX(theme.id)) themeId',
            'sales_channel.maintenance maintenance',
            // @deprecated tag:v6.8.0 - remove the COALESCE fallback to the deprecated `maintenance_ip_whitelist` column
            'COALESCE(sales_channel.maintenance_ip_allowlist, sales_channel.maintenance_ip_whitelist) maintenanceIpAllowlist',
            'snippet_set.iso as locale',
            'theme.technical_name as themeName',
            'parentTheme.technical_name as parentThemeName',
        );

        $query->from('sales_channel');
        $query->innerJoin('sales_channel', 'sales_channel_domain', 'domain', 'domain.sales_channel_id = sales_channel.id');
        $query->innerJoin('domain', 'snippet_set', 'snippet_set', 'snippet_set.id = domain.snippet_set_id');
        $query->leftJoin('sales_channel', 'theme_sales_channel', 'theme_sales_channel', 'sales_channel.id = theme_sales_channel.sales_channel_id');
        $query->leftJoin('theme_sales_channel', 'theme', 'theme', 'theme_sales_channel.theme_id = theme.id');
        $query->leftJoin('theme', 'theme', 'parentTheme', 'theme.parent_theme_id = parentTheme.id');
        $query->where('sales_channel.type_id = UNHEX(:typeId)');
        $query->andWhere('sales_channel.active');
        $query->setParameter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        /** @var array<string, Domain> $domains */
        $domains = FetchModeHelper::groupUnique($query->executeQuery()->fetchAllAssociative());

        return $domains;
    }
}
