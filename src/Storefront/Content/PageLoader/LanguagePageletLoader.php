<?php declare(strict_types=1);

namespace Shopware\Storefront\Content\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Content\Page\LanguagePageletStruct;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LanguagePageletLoader implements PageLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepository
     */
    private $languageRepository;

    public function __construct(
        EntityRepository $languageRepository
    ) {
        $this->languageRepository = $languageRepository;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    public function load(PageRequest $request, CheckoutContext $context): LanguagePageletStruct
    {
        $page = new LanguagePageletStruct();
        $salesChannel = $context->getSalesChannel();
        $page->setLanguages($this->getLanguages($context));
        $page->setLanguage($salesChannel->getLanguage());

        return $page;
    }

    private function getLanguages(CheckoutContext $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.salesChannels.id', $context->getSalesChannel()->getId()));

        return $this->languageRepository->search($criteria, $context->getContext());
    }
}
