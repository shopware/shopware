<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Offcanvas;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\SalesChannel\AbstractNavigationRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\SalesChannel\AbstractCurrencyRoute;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\RouteRequest\CurrencyRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\LanguageRouteRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('core')]
class OffcanvasLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractCurrencyRoute $currencyRoute,
        private readonly AbstractLanguageRoute $languageRoute,
        private readonly AbstractNavigationRoute $navigationRoute,
        private readonly EntityRepository $categoryRepository
    ) {
    }

    public function load(Request $request, SalesChannelContext $context): Offcanvas
    {
        $categoryId = $request->get('navigationId', $context->getSalesChannel()->getNavigationCategoryId());

        $response = $this->navigationRoute->children($categoryId, $request, $context);

        $page = new Offcanvas(
            categories: $response->getCategories(),
            category: $this->loadCategory($categoryId, $context->getContext()),
            currencies: $this->loadCurrencies($request, $context),
            languages: $this->loadLanguages($context, $request)
        );

        $this->eventDispatcher->dispatch(new OffcanvasLoadedEvent($page, $context, $request));

        return $page;
    }

    private function loadLanguages(SalesChannelContext $context, Request $request): ?LanguageCollection
    {
        if (!$request->get('languages', false)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->setTitle('header::languages');

        $criteria->addFilter(
            new EqualsFilter('language.salesChannelDomains.salesChannelId', $context->getSalesChannel()->getId())
        );

        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        if (!Feature::isActive('cache_rework')) {
            $criteria->addAssociation('productSearchConfig');
        }
        $apiRequest = new Request();

        $event = new LanguageRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->languageRoute->load($event->getStoreApiRequest(), $context, $criteria)->getLanguages();
    }

    private function loadCurrencies(Request $request, SalesChannelContext $context): ?CurrencyCollection
    {
        if (!$request->get('currencies', false)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->setTitle('header::currencies');

        $event = new CurrencyRouteRequestEvent($request, new Request(), $context);
        $this->eventDispatcher->dispatch($event);

        return $this->currencyRoute
            ->load($event->getStoreApiRequest(), $context, $criteria)
            ->getCurrencies();
    }

    private function loadCategory(string $categoryId, Context $context): CategoryEntity
    {
        $criteria = new Criteria([$categoryId]);

        $category = $this->categoryRepository->search($criteria, $context)->first();

        if (!$category instanceof CategoryEntity) {
            throw CategoryException::categoryNotFound($categoryId);
        }

        return $category;
    }
}
