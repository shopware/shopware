<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Subscriber;

use Shopware\Core\Content\Category\CategoryService;
use Shopware\Core\Content\Category\Event\NavigationRouteValidateEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[Package('inventory')]
class NavigationRouteValidateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            NavigationRouteValidateEvent::class => 'validate',
        ];
    }

    public function validate(NavigationRouteValidateEvent $event): void
    {
        $salesChannel = $event->getSalesChannelContext()->getSalesChannel();

        $ids = array_filter([
            $salesChannel->getFooterCategoryId(),
            $salesChannel->getServiceCategoryId(),
            $salesChannel->getNavigationCategoryId(),
        ]);

        foreach ($ids as $id) {
            if ($this->categoryService->isChildCategory($event->getActiveId(), $event->getPath(), $id)) {
                $event->setValid(true);
            }
        }
    }
}
