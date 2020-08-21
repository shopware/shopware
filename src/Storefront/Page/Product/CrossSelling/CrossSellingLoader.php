<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CrossSellingLoader
{
    /**
     * @var AbstractProductCrossSellingRoute
     */
    private $route;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(AbstractProductCrossSellingRoute $route, EventDispatcherInterface $eventDispatcher)
    {
        $this->route = $route;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(string $productId, SalesChannelContext $context): CrossSellingLoaderResult
    {
        $result = $this->route
            ->load($productId, new Request(), $context, new Criteria())
            ->getResult();

        $mapped = new CrossSellingLoaderResult();
        foreach ($result as $element) {
            $mapped->add(CrossSellingElement::createFrom($element));
        }

        $this->eventDispatcher->dispatch(new CrossSellingLoadedEvent($mapped, $context));

        return $mapped;
    }
}
