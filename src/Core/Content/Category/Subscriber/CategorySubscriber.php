<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Subscriber;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class CategorySubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractCategoryUrlGenerator $categoryUrlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CategoryEvents::CATEGORY_LOADED_EVENT => 'entityLoaded',
            'sales_channel.' . CategoryEvents::CATEGORY_LOADED_EVENT => [['entityLoaded'], ['addSeoLinks']],
            'sales_channel.category.partial_loaded' => 'addSeoLinks',
        ];
    }

    /**
     * @param EntityLoadedEvent<CategoryEntity> $event
     */
    public function entityLoaded(EntityLoadedEvent $event): void
    {
        $salesChannelId = $event instanceof SalesChannelEntityLoadedEvent ? $event->getSalesChannelContext()->getSalesChannelId() : null;

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

    /**
     * @param SalesChannelEntityLoadedEvent<SalesChannelCategoryEntity|PartialEntity> $event
     */
    public function addSeoLinks(SalesChannelEntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $category) {
            if ($category->get('type') !== CategoryDefinition::TYPE_LINK
                || $category->getTranslation('linkType') === CategoryDefinition::LINK_TYPE_EXTERNAL
                || !$category->getTranslation('internalLink')) {
                continue;
            }

            if ($category instanceof PartialEntity) {
                $tmpCategory = (new CategoryEntity())->assign([
                    'id' => $category->get('id'),
                    'type' => $category->get('type'),
                    'linkType' => $category->getTranslation('linkType'),
                    'internalLink' => $category->getTranslation('internalLink'),
                ]);
            } else {
                $tmpCategory = $category;
            }

            $category->assign([
                'seoLink' => $this->categoryUrlGenerator->generate($tmpCategory, $event->getSalesChannelContext()->getSalesChannel()),
            ]);
        }
    }
}
