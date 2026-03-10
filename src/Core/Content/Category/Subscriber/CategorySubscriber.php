<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
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
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.category.loaded' => 'salesChannelCategoryLoaded',
            'category.written' => 'onCategoryWritten',
        ];
    }

    /**
     * @param SalesChannelEntityLoadedEvent<SalesChannelCategoryEntity> $event
     */
    public function salesChannelCategoryLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        $salesChannel = $event->getSalesChannelContext()->getSalesChannel();
        $salesChannelId = $salesChannel->getId();

        $systemDefaultLayout = $this->systemConfigService->getString(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY);
        $salesChannelDefaultLayout = $this->systemConfigService->getString(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY, $salesChannelId);

        foreach ($event->getEntities() as $category) {
            $category->assign([
                'seoUrl' => $this->categoryUrlGenerator->generate($category, $salesChannel),
            ]);

            if ($salesChannelDefaultLayout === '') {
                continue;
            }

            if ($category->getCmsPageId() !== null && $category->getCmsPageId() !== $systemDefaultLayout) {
                continue;
            }

            $category->setCmsPageId($salesChannelDefaultLayout);
        }
    }

    public function onCategoryWritten(EntityWrittenEvent $event): void
    {
        $defaultCmsPageId = $this->systemConfigService->getString(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY);
        if ($defaultCmsPageId === '') {
            return;
        }

        $ids = [];
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();

            if (!empty($payload['cmsPageId'])) {
                continue;
            }

            $needsDefault = match ($result->getOperation()) {
                EntityWriteResult::OPERATION_INSERT => true,
                EntityWriteResult::OPERATION_UPDATE => \array_key_exists('cmsPageId', $payload),
                default => false,
            };

            if (!$needsDefault) {
                continue;
            }

            $primaryKey = $result->getPrimaryKey();
            $ids[] = \is_array($primaryKey) ? $primaryKey['id'] : $primaryKey;
        }

        if ($ids === []) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE `category` SET `cms_page_id` = :cmsPageId WHERE `id` IN (:ids) AND `cms_page_id` IS NULL',
            [
                'cmsPageId' => Uuid::fromHexToBytes($defaultCmsPageId),
                'ids' => Uuid::fromHexToBytesList($ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );
    }
}
