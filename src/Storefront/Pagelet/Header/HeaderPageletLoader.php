<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Content\Navigation\Service\NavigationTreeLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Language\LanguageCollection;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class HeaderPageletLoader implements PageLoaderInterface
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
     * @var NavigationTreeLoader
     */
    private $navigationLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $currencyRepository,
        NavigationTreeLoader $navigationLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->languageRepository = $languageRepository;
        $this->currencyRepository = $currencyRepository;
        $this->navigationLoader = $navigationLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $context): HeaderPagelet
    {
        $navigationId = $request->get(
            'navigationId',
            $context->getSalesChannel()->getNavigationId()
        );

        if (!$navigationId) {
            throw new MissingRequestParameterException('navigationId');
        }

        $navigation = $this->navigationLoader->load((string) $navigationId, $context);

        /** @var LanguageCollection $languages */
        $languages = $this->loadLanguages($context);

        /** @var CurrencyCollection $currencies */
        $currencies = $this->loadCurrencies($context);

        $page = new HeaderPagelet(
            $navigation,
            $languages,
            $currencies,
            $languages->get($context->getContext()->getLanguageId()),
            $context->getCurrency()
        );

        $this->eventDispatcher->dispatch(
            HeaderPageletLoadedEvent::NAME,
            new HeaderPageletLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadLanguages(SalesChannelContext $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.salesChannels.id', $context->getSalesChannel()->getId()));

        return $this->languageRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function loadCurrencies(SalesChannelContext $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('currency.salesChannels.id', $context->getSalesChannel()->getId()));

        return $this->currencyRepository->search($criteria, $context->getContext())->getEntities();
    }
}
