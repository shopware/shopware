<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Uuid\Uuid;

class AddCustomerAffiliateAndCampaignCodeAction extends FlowAction
{
    private Connection $connection;

    private EntityRepositoryInterface $customerRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $customerRepository
    ) {
        $this->connection = $connection;
        $this->customerRepository = $customerRepository;
    }

    public static function getName(): string
    {
        return 'action.add.customer.affiliate.and.campaign.code';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [CustomerAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        $config = $event->getConfig();

        if (!\array_key_exists('affiliateCode', $config) || !\array_key_exists('campaignCode', $config)) {
            return;
        }

        $baseEvent = $event->getEvent();

        if (!$baseEvent instanceof CustomerAware) {
            return;
        }

        $customerId = $baseEvent->getCustomerId();
        $customerData = $this->getAffiliateAndCampaignCodeFromCustomerId($customerId);

        if (empty($customerData)) {
            return;
        }

        $affiliateCode = $customerData['affiliate_code'];
        if ($affiliateCode === null || $config['affiliateCode']['upsert']) {
            $affiliateCode = $config['affiliateCode']['value'];
        }

        $campaignCode = $customerData['campaign_code'];
        if ($campaignCode === null || $config['campaignCode']['upsert']) {
            $campaignCode = $config['campaignCode']['value'];
        }

        $data = [];
        if ($affiliateCode !== $customerData['affiliate_code']) {
            $data['affiliateCode'] = $affiliateCode;
        }

        if ($campaignCode !== $customerData['campaign_code']) {
            $data['campaignCode'] = $campaignCode;
        }

        if (empty($data)) {
            return;
        }

        $data['id'] = $customerId;

        $this->customerRepository->update([$data], $baseEvent->getContext());
    }

    private function getAffiliateAndCampaignCodeFromCustomerId(string $customerId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['affiliate_code', 'campaign_code']);
        $query->from('customer');
        $query->where('id = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($customerId));

        if (!$data = $query->execute()->fetchAssociative()) {
            return [];
        }

        return $data;
    }
}
