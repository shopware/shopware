<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Language\LanguageCollection;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class HeaderPageletLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var NavigationLoader
     */
    private $navigationLoader;

    /**
     * @var SalesChannelRepository
     */
    private $categoryRepository;

    public function __construct(
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $currencyRepository,
        SalesChannelRepository $categoryRepository,
        NavigationLoader $navigationLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->languageRepository = $languageRepository;
        $this->currencyRepository = $currencyRepository;
        $this->navigationLoader = $navigationLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->categoryRepository = $categoryRepository;
    }

    public function load(Request $request, SalesChannelContext $context): HeaderPagelet
    {
        $navigationId = $request->get('navigationId', $context->getSalesChannel()->getNavigationCategoryId());

        if (!$navigationId) {
            throw new MissingRequestParameterException('navigationId');
        }

        $category = $this->navigationLoader->load(
            (string) $navigationId,
            $context,
            $context->getSalesChannel()->getNavigationCategoryId()
        );

        $offCanvasNavigation = $this->navigationLoader->loadLevel((string) $navigationId, $context);

        /** @var LanguageCollection $languages */
        $languages = $this->loadLanguages($context);

        /** @var CurrencyCollection $currencies */
        $currencies = $this->loadCurrencies($context);

        $page = new HeaderPagelet(
            $category,
            $offCanvasNavigation,
            $languages,
            $currencies,
            $languages->get($context->getContext()->getLanguageId()),
            $context->getCurrency(),
            $this->loadServiceMenu($context)
        );

        $this->eventDispatcher->dispatch(
            new HeaderPageletLoadedEvent($page, $context, $request),
            HeaderPageletLoadedEvent::NAME
        );

        return $page;
    }

    private function loadLanguages(SalesChannelContext $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('translationCode');
        $criteria->addFilter(new EqualsFilter('language.salesChannelDomains.salesChannelId', $context->getSalesChannel()->getId()));

        return $this->languageRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function loadCurrencies(SalesChannelContext $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('currency.salesChannels.id', $context->getSalesChannel()->getId()));

        return $this->currencyRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function loadServiceMenu(SalesChannelContext $context): CategoryCollection
    {
        $serviceId = $context->getSalesChannel()->getServiceCategoryId();

        if (!$serviceId) {
            return new CategoryCollection();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.parentId', $serviceId));

        $result = $this->categoryRepository->search($criteria, $context);

        /** @var CategoryCollection $categories */
        $categories = $result->getEntities();

        return $categories->sortByPosition();
    }
}
