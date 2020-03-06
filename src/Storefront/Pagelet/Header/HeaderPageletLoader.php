<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class HeaderPageletLoader implements HeaderPageletLoaderInterface
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var NavigationLoader
     */
    private $navigationLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $languageRepository;

    public function __construct(
        SalesChannelRepositoryInterface $languageRepository,
        SalesChannelRepositoryInterface $currencyRepository,
        SalesChannelRepositoryInterface $categoryRepository,
        NavigationLoader $navigationLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->currencyRepository = $currencyRepository;
        $this->categoryRepository = $categoryRepository;
        $this->navigationLoader = $navigationLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->languageRepository = $languageRepository;
    }

    /**
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): HeaderPagelet
    {
        $navigationCategoryId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
        $navigationId = $request->get('navigationId', $navigationCategoryId);

        if (!$navigationId) {
            throw new MissingRequestParameterException('navigationId');
        }

        $navigation = $this->navigationLoader->load(
            (string) $navigationId,
            $salesChannelContext,
            $navigationCategoryId,
            $salesChannelContext->getSalesChannel()->getNavigationCategoryDepth()
        );

        $languages = $this->loadLanguages($salesChannelContext);
        $currencies = $this->loadCurrencies($salesChannelContext);

        $pagelet = new HeaderPagelet(
            $navigation,
            $languages,
            $currencies,
            $languages->get($salesChannelContext->getContext()->getLanguageId()),
            $salesChannelContext->getCurrency(),
            $this->loadServiceMenu($salesChannelContext)
        );

        $this->eventDispatcher->dispatch(new HeaderPageletLoadedEvent($pagelet, $salesChannelContext, $request));

        return $pagelet;
    }

    private function loadLanguages(SalesChannelContext $salesChannelContext): LanguageCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('translationCode');

        $criteria->addFilter(new EqualsFilter(
            'language.salesChannelDomains.salesChannelId',
            $salesChannelContext->getSalesChannel()->getId()
        ));

        /** @var LanguageCollection $languages */
        $languages = $this->languageRepository->search($criteria, $salesChannelContext)->getEntities();

        return $languages;
    }

    private function loadCurrencies(SalesChannelContext $salesChannelContext): CurrencyCollection
    {
        /** @var CurrencyCollection $currencyCollection */
        $currencyCollection = $this->currencyRepository->search(new Criteria(), $salesChannelContext)->getEntities();

        return $currencyCollection;
    }

    private function loadServiceMenu(SalesChannelContext $salesChannelContext): CategoryCollection
    {
        $serviceId = $salesChannelContext->getSalesChannel()->getServiceCategoryId();

        if ($serviceId === null) {
            return new CategoryCollection();
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('category.parentId', $serviceId))
            ->addFilter(new EqualsFilter('category.active', true));

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $salesChannelContext)->getEntities();

        return $categories->sortByPosition();
    }
}
