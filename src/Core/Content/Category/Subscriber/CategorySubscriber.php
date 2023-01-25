<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Subscriber;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('content')]
class CategorySubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CategoryEvents::CATEGORY_LOADED_EVENT => 'entityLoaded',
            'sales_channel.' . CategoryEvents::CATEGORY_LOADED_EVENT => 'entityLoaded',
        ];
    }

    public function entityLoaded(EntityLoadedEvent $event): void
    {
        $salesChannelId = $event instanceof SalesChannelEntityLoadedEvent ? $event->getSalesChannelContext()->getSalesChannelId() : null;

        /** @var CategoryEntity $category */
        foreach ($event->getEntities() as $category) {
            $categoryCmsPageId = $category->getCmsPageId();

            // continue if cms page is given and was not set in the subscriber
            if ($categoryCmsPageId !== null && !$category->getCmsPageIdSwitched()) {
                continue;
            }

            // continue if cms page is given and not the overall default
            if ($categoryCmsPageId !== null && $categoryCmsPageId !== $this->systemConfigService->get(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY)) {
                continue;
            }

            $userDefault = $this->systemConfigService->get(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY, $salesChannelId);

            // cms page is not given in system config
            if ($userDefault === null) {
                continue;
            }

            /** @var string $userDefault */
            $category->setCmsPageId($userDefault);

            // mark cms page as set in the subscriber
            $category->setCmsPageIdSwitched(true);
        }
    }
}
