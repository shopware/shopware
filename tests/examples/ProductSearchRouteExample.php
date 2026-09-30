<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use Shopware\Core\Content\Product\Extension\ProductSearchRouteExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves the product search yourself — e.g. against an external search service —
 * instead of the core listing-loader based search.
 */
readonly class ProductSearchRouteExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductSearchRouteExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(ProductSearchRouteExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->request, $event->context, $event->criteria

        $result = new ProductListingResult(
            ProductDefinition::ENTITY_NAME,
            0,
            new ProductCollection(),
            null,
            $event->criteria,
            $event->context->getContext(),
        );

        $event->result = new ProductSearchRouteResponse($result);

        // stop propagation so the core product search is skipped
        $event->stopPropagation();
    }
}
