<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Routing\RouterInterface;

class ProductLinkLoader
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelDomainRepository;

    public function __construct(RouterInterface $router, EntityRepositoryInterface $salesChannelDomainRepository)
    {
        $this->router = $router;
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
    }

    public function load(string $productId, Context $context): ProductLinkCollection
    {
        $result = new ProductLinkCollection();
        $path = $this->router->generate('frontend.detail.page', ['productId' => $productId]);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannel.navigationCategory.nestedProducts.id', $productId))
            ->addAssociation('salesChannel');
        $entitySearchResult = $this->salesChannelDomainRepository->search($criteria, $context);

        /** @var SalesChannelDomainEntity $salesChannelDomain */
        foreach ($entitySearchResult->getElements() as $salesChannelDomain) {
            $result->add(
                (new ProductLink())
                ->setPath($path)
                ->setProductId($productId)
                ->setSalesChannelDomain($salesChannelDomain->getUrl())
                ->setSalesChannelId($salesChannelDomain->getSalesChannelId())
                ->setSalesChannelName($salesChannelDomain->getSalesChannel()->getName())
            );
        }

        return $result;
    }
}
