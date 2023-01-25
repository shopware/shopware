<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @phpstan-import-type Domain from AbstractDomainLoader
 */
#[Package('storefront')]
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
     * @return array<string, Domain>
     */
    public function load(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select([
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
            'sales_channel.maintenance_ip_whitelist maintenanceIpWhitelist',
            'snippet_set.iso as locale',
            'theme.technical_name as themeName',
            'parentTheme.technical_name as parentThemeName',
        ]);

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
