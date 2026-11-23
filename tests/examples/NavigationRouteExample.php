<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Extension\NavigationRouteExtension;
use Shopware\Core\Content\Category\SalesChannel\NavigationRouteResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves the navigation categories yourself — e.g. a customer-group filtered
 * tree or an external source — instead of the core navigation loader.
 */
readonly class NavigationRouteExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            NavigationRouteExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(NavigationRouteExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->activeId, $event->rootId, $event->request, $event->context, $event->criteria

        $category = (new CategoryEntity())->assign(['id' => 'example-category']);

        $event->result = new NavigationRouteResponse(new CategoryCollection([$category]));

        // stop propagation so the core navigation loading is skipped
        $event->stopPropagation();
    }
}
