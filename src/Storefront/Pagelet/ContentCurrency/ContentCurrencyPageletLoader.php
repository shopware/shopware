<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentCurrency;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\InternalRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContentCurrencyPageletLoader
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

    public function load(InternalRequest $request, CheckoutContext $context): ContentCurrencyPageletStruct
    {
        $page = new ContentCurrencyPageletStruct();
        $page->setCurrencies($this->getCurrencies($context));
        $page->setCurrency($context->getCurrency());

        return $page;
    }

    private function getCurrencies(CheckoutContext $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('currency.salesChannels.id', $context->getSalesChannel()->getId()));

        return $this->currencyRepository->search($criteria, $context->getContext());
    }
}
