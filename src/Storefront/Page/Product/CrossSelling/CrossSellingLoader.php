<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.4.0 - Use `\Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute` instead
 */
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
            ->load($productId, $context)
            ->getResult();

        $mapped = new CrossSellingLoaderResult();
        foreach ($result as $element) {
            $mapped->add(CrossSellingElement::createFrom($element));
        }

        $this->eventDispatcher->dispatch(new CrossSellingLoadedEvent($mapped, $context));

        return $mapped;
    }
}
