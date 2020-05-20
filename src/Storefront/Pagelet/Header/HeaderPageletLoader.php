<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\Currency\SalesChannel\AbstractCurrencyRoute;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

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
    private $languagePageRoute;

    /**
     * @var NavigationLoaderInterface
     */
    private $navigationLoader;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        AbstractCurrencyRoute $currencyPageRoute,
        AbstractLanguageRoute $languagePageRoute,
        NavigationLoaderInterface $navigationLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->currencyRoute = $currencyPageRoute;
        $this->languagePageRoute = $languagePageRoute;
        $this->navigationLoader = $navigationLoader;
    }

    /**
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): HeaderPagelet
    {
        $salesChannel = $salesChannelContext->getSalesChannel();
        $navigationId = $request->get('navigationId', $salesChannel->getNavigationCategoryId());

        if (!$navigationId) {
            throw new MissingRequestParameterException('navigationId');
        }

        $languages = $this->getLanguages($salesChannelContext);

        $navigation = $this->navigationLoader->load(
            (string) $navigationId,
            $salesChannelContext,
            $salesChannel->getNavigationCategoryId(),
            $salesChannel->getNavigationCategoryDepth()
        );

        $currencies = $this->currencyRoute->load(new Request(), $salesChannelContext)->getCurrencies();

        $page = new HeaderPagelet(
            $navigation,
            $languages,
            $currencies,
            $languages->get($salesChannelContext->getContext()->getLanguageId()),
            $salesChannelContext->getCurrency(),
            $this->getServiceMenu($salesChannelContext)
        );

        $this->eventDispatcher->dispatch(
            new HeaderPageletLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getServiceMenu(SalesChannelContext $salesChannelContext): CategoryCollection
    {
        $serviceId = $salesChannelContext->getSalesChannel()->getServiceCategoryId();

        if ($serviceId === null) {
            return new CategoryCollection();
        }

        $navigation = $this->navigationLoader->load($serviceId, $salesChannelContext, $serviceId, 1);

        return new CategoryCollection(array_map(static function (TreeItem $treeItem) {
            return $treeItem->getCategory();
        }, $navigation->getTree()));
    }

    private function getLanguages(SalesChannelContext $context): LanguageCollection
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new EqualsFilter('language.salesChannelDomains.salesChannelId', $context->getSalesChannel()->getId())
        );

        return $this->languagePageRoute->load(new Request(), $context, $criteria)->getLanguages();
    }
}
