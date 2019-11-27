<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Sitemap;

use Shopware\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapListerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class SitemapPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SitemapListerInterface
     */
    private $sitemapLister;

    /**
     * @var SitemapExporterInterface
     */
    private $sitemapExporter;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var Session
     */
    private $session;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        SitemapListerInterface $sitemapLister,
        SitemapExporterInterface $sitemapExporter,
        SystemConfigService $systemConfigService,
        Session $session
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->sitemapLister = $sitemapLister;
        $this->sitemapExporter = $sitemapExporter;
        $this->systemConfigService = $systemConfigService;
        $this->session = $session;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): SitemapPage
    {
        $sitemaps = $this->sitemapLister->getSitemaps($salesChannelContext);

        // If there are no sitemaps yet (or they are too old) and the generation strategy is "live", generate sitemaps
        if ((int) $this->systemConfigService->get('core.sitemap.sitemapRefreshStrategy') === SitemapExporterInterface::STRATEGY_LIVE) {
            // Close session to prevent session locking from waiting in case there is another request coming in
            $this->session->save();

            try {
                $this->generateSitemap($salesChannelContext, true);
            } catch (AlreadyLockedException $exception) {
                // Silent catch, lock couldn't be acquired. Some other process already generates the sitemap.
            }

            $sitemaps = $this->sitemapLister->getSitemaps($salesChannelContext);
        }

        $page = new SitemapPage();
        $page->setSitemaps($sitemaps);

        $this->eventDispatcher->dispatch(
            new SitemapPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function generateSitemap(SalesChannelContext $salesChannelContext, bool $force, ?string $lastProvider = null, ?int $offset = null): void
    {
        $result = $this->sitemapExporter->generate($salesChannelContext, $force, $lastProvider, $offset);
        if ($result->isFinish() === false) {
            $this->generateSitemap($salesChannelContext, $force, $result->getProvider(), $result->getOffset());
        }
    }
}
