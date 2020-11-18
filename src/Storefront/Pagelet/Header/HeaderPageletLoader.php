<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\Currency\SalesChannel\AbstractCurrencyRoute;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\CurrencyRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\LanguageRouteRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Decoratable()
 */
class HeaderPageletLoader implements HeaderPageletLoaderInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractCurrencyRoute
     */
    private $currencyRoute;

    /**
     * @var AbstractLanguageRoute
     */
    private $languageRoute;

    /**
     * @var NavigationLoaderInterface
     */
    private $navigationLoader;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        AbstractCurrencyRoute $currencyRoute,
        AbstractLanguageRoute $languageRoute,
        NavigationLoaderInterface $navigationLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->currencyRoute = $currencyRoute;
        $this->languageRoute = $languageRoute;
        $this->navigationLoader = $navigationLoader;
    }

    /**
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $context): HeaderPagelet
    {
        $salesChannel = $context->getSalesChannel();
        $navigationId = $request->get('navigationId', $salesChannel->getNavigationCategoryId());

        if (!$navigationId) {
            throw new MissingRequestParameterException('navigationId');
        }

        $languages = $this->getLanguages($context, $request);

        $event = new CurrencyRouteRequestEvent($request, new Request(), $context);
        $this->eventDispatcher->dispatch($event);

        $navigation = $this->navigationLoader->load(
            (string) $navigationId,
            $context,
            $salesChannel->getNavigationCategoryId(),
            $salesChannel->getNavigationCategoryDepth()
        );

        $criteria = new Criteria();
        $criteria->setTitle('header::currencies');

        $currencies = $this->currencyRoute
            ->load($event->getStoreApiRequest(), $context, $criteria)
            ->getCurrencies();

        $page = new HeaderPagelet(
            $navigation,
            $languages,
            $currencies,
            $languages->get($context->getContext()->getLanguageId()),
            $context->getCurrency(),
            $this->getServiceMenu($context)
        );

        $this->eventDispatcher->dispatch(new HeaderPageletLoadedEvent($page, $context, $request));

        return $page;
    }

    private function getServiceMenu(SalesChannelContext $context): CategoryCollection
    {
        $serviceId = $context->getSalesChannel()->getServiceCategoryId();

        if ($serviceId === null) {
            return new CategoryCollection();
        }

        $navigation = $this->navigationLoader->load($serviceId, $context, $serviceId, 1);

        return new CategoryCollection(array_map(static function (TreeItem $treeItem) {
            return $treeItem->getCategory();
        }, $navigation->getTree()));
    }

    private function getLanguages(SalesChannelContext $context, Request $request): LanguageCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('header::languages');

        $criteria->addFilter(
            new EqualsFilter('language.salesChannelDomains.salesChannelId', $context->getSalesChannel()->getId())
        );

        $apiRequest = new Request();

        $event = new LanguageRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->languageRoute->load($event->getStoreApiRequest(), $context, $criteria)->getLanguages();
    }
}
