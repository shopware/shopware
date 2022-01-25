<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Uuid\Uuid;

class AddOrderAffiliateAndCampaignCodeAction extends FlowAction
{
    private Connection $connection;

    private EntityRepositoryInterface $orderRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $orderRepository
    ) {
        $this->connection = $connection;
        $this->orderRepository = $orderRepository;
    }

    public static function getName(): string
    {
        return 'action.add.order.affiliate.and.campaign.code';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        $config = $event->getConfig();

        if (!\array_key_exists('affiliateCode', $config) || !\array_key_exists('campaignCode', $config)) {
            return;
        }

        $baseEvent = $event->getEvent();

        if (!$baseEvent instanceof OrderAware) {
            return;
        }

        $orderId = $baseEvent->getOrderId();
        $orderData = $this->getAffiliateAndCampaignCodeFromOrderId($orderId);

        if (empty($orderData)) {
            return;
        }

        $affiliateCode = $orderData['affiliate_code'];
        if ($affiliateCode === null || $config['affiliateCode']['upsert']) {
            $affiliateCode = $config['affiliateCode']['value'];
        }

        $campaignCode = $orderData['campaign_code'];
        if ($campaignCode === null || $config['campaignCode']['upsert']) {
            $campaignCode = $config['campaignCode']['value'];
        }

        $data = [];
        if ($affiliateCode !== $orderData['affiliate_code']) {
            $data['affiliateCode'] = $affiliateCode;
        }

        if ($campaignCode !== $orderData['campaign_code']) {
            $data['campaignCode'] = $campaignCode;
        }

        if (empty($data)) {
            return;
        }

        $data['id'] = $orderId;

        $this->orderRepository->update([$data], $baseEvent->getContext());
    }

    private function getAffiliateAndCampaignCodeFromOrderId(string $orderId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['affiliate_code', 'campaign_code']);
        $query->from('`order`');
        $query->where('id = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($orderId));

        if (!$data = $query->execute()->fetchAssociative()) {
            return [];
        }

        return $data;
    }
}
