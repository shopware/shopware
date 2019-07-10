<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Language\LanguageCollection;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class HeaderPageletLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelDomainRepository;

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

    public function __construct(
        EntityRepositoryInterface $salesChannelDomainRepository,
        SalesChannelRepositoryInterface $currencyRepository,
        SalesChannelRepositoryInterface $categoryRepository,
        NavigationLoader $navigationLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
        $this->currencyRepository = $currencyRepository;
        $this->categoryRepository = $categoryRepository;
        $this->navigationLoader = $navigationLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): HeaderPagelet
    {
        $navigationId = $request->get('navigationId', $salesChannelContext->getSalesChannel()->getNavigationCategoryId());

        if (!$navigationId) {
            throw new MissingRequestParameterException('navigationId');
        }

        $category = $this->navigationLoader->load(
            (string) $navigationId,
            $salesChannelContext,
            $salesChannelContext->getSalesChannel()->getNavigationCategoryId()
        );

        $offCanvasNavigation = $this->navigationLoader->loadLevel((string) $navigationId, $salesChannelContext);

        $languages = $this->loadLanguages($salesChannelContext);

        $currencies = $this->loadCurrencies($salesChannelContext);

        $page = new HeaderPagelet(
            $category,
            $offCanvasNavigation,
            $languages,
            $currencies,
            $languages->get($salesChannelContext->getContext()->getLanguageId()),
            $salesChannelContext->getCurrency(),
            $this->loadServiceMenu($salesChannelContext)
        );

        $this->eventDispatcher->dispatch(
            new HeaderPageletLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function loadLanguages(SalesChannelContext $salesChannelContext): LanguageCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannel()->getId()))
            ->addAssociationPath('language.translationCode');

        $domains = $this->salesChannelDomainRepository->search($criteria, $salesChannelContext->getContext())->getEntities();

        $languageCollection = new LanguageCollection();
        /** @var SalesChannelDomainEntity $domain */
        foreach ($domains as $domain) {
            $languageCollection->add($domain->getLanguage());
        }

        return $languageCollection;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function loadCurrencies(SalesChannelContext $salesChannelContext): CurrencyCollection
    {
        /** @var CurrencyCollection $currencyCollection */
        $currencyCollection = $this->currencyRepository->search(new Criteria(), $salesChannelContext)->getEntities();

        return $currencyCollection;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function loadServiceMenu(SalesChannelContext $salesChannelContext): CategoryCollection
    {
        $serviceId = $salesChannelContext->getSalesChannel()->getServiceCategoryId();

        if (!$serviceId) {
            return new CategoryCollection();
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('category.parentId', $serviceId));

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($criteria, $salesChannelContext)->getEntities();

        return $categories->sortByPosition();
    }
}
