<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteNotFoundException;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @deprecated tag:v6.3.0
 */
class SeoUrlTemplateLoader
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlTemplateRepository;

    /**
     * @var SeoUrlRouteRegistry
     */
    private $routeRegistry;

    public function __construct(Connection $connection, EntityRepositoryInterface $seoUrlTemplateRepository, SeoUrlRouteRegistry $routeRegistory)
    {
        $this->connection = $connection;
        $this->seoUrlTemplateRepository = $seoUrlTemplateRepository;
        $this->routeRegistry = $routeRegistory;
    }

    public function getTemplateGroups(string $routeName, array $salesChannelEntities = []): array
    {
        $groups = FetchModeHelper::group(
            $this->connection->executeQuery(
                'SELECT
                   LOWER(HEX(domains.language_id)) as languageId,
                   LOWER(HEX(sales_channel.id)) as salesChannelId,
                   sales_channel.short_name shortName,
                   templates.template
                 FROM sales_channel_domain as domains
                 INNER JOIN sales_channel
                   ON domains.sales_channel_id = sales_channel.id
                 LEFT JOIN seo_url_template templates
                   ON templates.sales_channel_id = sales_channel.id
                   AND templates.route_name = :routeName
                 WHERE sales_channel.active',
                ['routeName' => $routeName]
            )->fetchAll()
        );
        $defaultTemplate = $this->getTemplateString(null, $routeName);

        $data = [];

        /*
         * Foreach language, group all salesChannels by template
         */
        foreach ($groups as $languageId => $salesChannels) {
            $tmpGroups = [$defaultTemplate => [null]];

            foreach ($salesChannels as $salesChannel) {
                $template = $salesChannel['template'] ?? $defaultTemplate;

                if (!isset($tmpGroups[$template])) {
                    $tmpGroups[$template] = [];
                }
                $tmpGroups[$template][] = $salesChannel['salesChannelId'];
            }

            $templateGroups = [];
            foreach ($tmpGroups as $template => $salesChannelIds) {
                $tmpSalesChannelEntities = array_filter($salesChannelEntities, function (?SalesChannelEntity $value) use ($salesChannelIds) {
                    if ($value === null) {
                        return in_array(null, $salesChannelIds, true);
                    }

                    return in_array($value->getId(), $salesChannelIds, true);
                });
                $templateGroups[] = new TemplateGroup($languageId, $template, $salesChannelIds, $tmpSalesChannelEntities);
            }

            $data[$languageId] = $templateGroups;
        }

        // there needs to be at least one template
        if (!isset($data[Defaults::LANGUAGE_SYSTEM])) {
            $data[Defaults::LANGUAGE_SYSTEM] = [
                new TemplateGroup(Defaults::LANGUAGE_SYSTEM, $defaultTemplate, [null], [null]),
            ];
        }

        return $data;
    }

    private function getTemplateString(?string $salesChannelId, string $routeName): string
    {
        $route = $this->routeRegistry->findByRouteName($routeName);
        if (!$route) {
            throw new SeoUrlRouteNotFoundException($routeName);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('routeName', $routeName));

        /** @var SeoUrlTemplateEntity|null $seoUrlTemplate */
        $seoUrlTemplate = $this->seoUrlTemplateRepository->search($criteria, Context::createDefaultContext())->first();

        $template = $seoUrlTemplate ? $seoUrlTemplate->getTemplate() : null;

        return $template ?: $route->getConfig()->getTemplate();
    }
}
