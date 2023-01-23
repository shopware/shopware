<?php declare(strict_types=1);

namespace Shopware\Administration\System\SalesChannel\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
#[Package('system-settings')]
class SalesChannelUserConfigSubscriber implements EventSubscriberInterface
{
    public const CONFIG_KEY = 'sales-channel-favorites';

    private EntityRepositoryInterface $userConfigRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepositoryInterface $userConfigRepository)
    {
        $this->userConfigRepository = $userConfigRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.deleted' => 'onSalesChannelDeleted',
        ];
    }

    public function onSalesChannelDeleted(EntityDeletedEvent $deletedEvent): void
    {
        $context = $deletedEvent->getContext();

        $deletedSalesChannelIds = $deletedEvent->getIds();

        $writeUserConfigs = [];
        foreach ($this->getAllFavoriteUserConfigs($context) as $userConfigEntity) {
            $salesChannelIds = $userConfigEntity->getValue();

            if ($salesChannelIds === null) {
                continue;
            }

            // Find matching IDs
            $matchingIds = array_intersect($deletedSalesChannelIds, $salesChannelIds);

            if (!$matchingIds) {
                continue;
            }

            // Removes the IDs from $matchingIds from the array
            $newUserConfigArray = array_diff($salesChannelIds, $matchingIds);
            $writeUserConfigs[] = [
                'id' => $userConfigEntity->getId(),
                'value' => array_values($newUserConfigArray),
            ];
        }

        $this->userConfigRepository->upsert($writeUserConfigs, $context);
    }

    private function getAllFavoriteUserConfigs(Context $context): UserConfigCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', self::CONFIG_KEY));

        /** @var UserConfigCollection $result */
        $result = $this->userConfigRepository->search($criteria, $context)->getEntities();

        return $result;
    }
}
