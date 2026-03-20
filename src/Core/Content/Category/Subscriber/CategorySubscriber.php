<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Subscriber;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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
            'sales_channel.category.loaded' => 'salesChannelCategoryLoaded',
            EntityWriteEvent::class => 'beforeWriteCategory',
        ];
    }

    /**
     * @param SalesChannelEntityLoadedEvent<SalesChannelCategoryEntity> $event
     */
    public function salesChannelCategoryLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        $salesChannel = $event->getSalesChannelContext()->getSalesChannel();

        foreach ($event->getEntities() as $category) {
            $category->assign([
                'seoUrl' => $this->categoryUrlGenerator->generate($category, $salesChannel),
            ]);
        }
    }

    public function beforeWriteCategory(EntityWriteEvent $event): void
    {
        $commands = $event->getCommandsForEntity(CategoryDefinition::ENTITY_NAME);
        if ($commands === []) {
            return;
        }

        $defaultCmsPageId = $this->systemConfigService->getString(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY);
        if ($defaultCmsPageId === '') {
            return;
        }

        $defaultCmsPageIdBytes = Uuid::fromHexToBytes($defaultCmsPageId);

        foreach ($commands as $command) {
            if ($command instanceof DeleteCommand) {
                continue;
            }

            if ($command instanceof InsertCommand) {
                if (!$command->hasField('cms_page_id') || $command->getPayload()['cms_page_id'] === null) {
                    $command->addPayload('cms_page_id', $defaultCmsPageIdBytes);
                }

                continue;
            }

            if ($command instanceof UpdateCommand) {
                if ($command->hasField('cms_page_id') && $command->getPayload()['cms_page_id'] === null) {
                    $command->addPayload('cms_page_id', $defaultCmsPageIdBytes);
                }
            }
        }
    }
}
