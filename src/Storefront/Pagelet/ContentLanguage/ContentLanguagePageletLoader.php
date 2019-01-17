<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentLanguage;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\InternalRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContentLanguagePageletLoader
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

    public function load(InternalRequest $request, CheckoutContext $context): ContentLanguagePageletStruct
    {
        $page = new ContentLanguagePageletStruct();
        $salesChannel = $context->getSalesChannel();
        $page->setLanguages($this->getLanguages($context));
        $page->setActiveLanguage($salesChannel->getLanguage());

        return $page;
    }

    private function getLanguages(CheckoutContext $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.salesChannels.id', $context->getSalesChannel()->getId()));

        return $this->languageRepository->search($criteria, $context->getContext());
    }
}
