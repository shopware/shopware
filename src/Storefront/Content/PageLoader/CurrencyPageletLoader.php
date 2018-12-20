<?php declare(strict_types=1);

namespace Shopware\Storefront\Content\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Content\Page\CurrencyPageletStruct;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CurrencyPageletLoader implements PageLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepository
     */
    private $currencyRepository;

    public function __construct(
        EntityRepository $currencyRepository
    ) {
        $this->currencyRepository = $currencyRepository;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    public function load(PageRequest $request, CheckoutContext $context): CurrencyPageletStruct
    {
        $page = new CurrencyPageletStruct();
        $salesChannel = $context->getSalesChannel();
        $page->setCurrencies($this->getCurrencies($context));
        $page->setCurrency($salesChannel->getCurrency());

        return $page;
    }

    private function getCurrencies(CheckoutContext $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('currency.salesChannels.id', $context->getSalesChannel()->getId()));

        return $this->currencyRepository->search($criteria, $context->getContext());
    }
}
