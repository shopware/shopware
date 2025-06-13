<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleCollection;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class RobotsPageLoader
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function load(Request $request, Context $context): RobotsPage
    {
        $page = new RobotsPage();

        $hostname = $request->server->get('HTTP_HOST');

        if (\is_string($hostname) && $hostname !== '') {
            $domains = $this->getDomains($hostname, $context);

            $page->setDomainRules($this->getDomainRules($hostname, $domains));
            $page->setSitemaps($this->getSitemaps($domains));
        } else {
            $page->setDomainRules(new DomainRuleCollection());
            $page->setSitemaps([]);
        }

        $this->eventDispatcher->dispatch(
            new RobotsPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    /**
     * @param non-empty-string $hostname
     */
    private function getDomains(string $hostname, Context $context): SalesChannelDomainCollection
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new ContainsFilter('url', $hostname))
            ->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
        ;

        return $this->salesChannelDomainRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param non-empty-string $hostname
     */
    private function getDomainRules(string $hostname, SalesChannelDomainCollection $domains): DomainRuleCollection
    {
        $domainRuleCollection = new DomainRuleCollection();

        $seenDomainHostnames = [];
        foreach ($domains as $domain) {
            $domainPath = explode($hostname, $domain->getUrl(), 2);
            $domainHostname = trim($domainPath[1] ?? '');

            // Skip hostnames which are available with http and https
            if (isset($seenDomainHostnames[$domainHostname])) {
                continue;
            }

            $seenDomainHostnames[$domainHostname] = true;
            $domainRules = trim($this->systemConfigService->getString('core.basicInformation.robotsRules', $domain->getSalesChannelId()));

            $domainRuleCollection->add(new DomainRuleStruct($domainRules, $domainHostname));
        }

        return $domainRuleCollection;
    }

    /**
     * @return list<string>
     */
    private function getSitemaps(SalesChannelDomainCollection $domains): array
    {
        $sitemaps = [];

        foreach ($domains as $domain) {
            $sitemaps[] = $domain->getUrl() . '/sitemap.xml';
        }

        return $sitemaps;
    }
}
