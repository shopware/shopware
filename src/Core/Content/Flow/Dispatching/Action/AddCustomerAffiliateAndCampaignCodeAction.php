<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\DelayAware;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;

class AddCustomerAffiliateAndCampaignCodeAction extends FlowAction
{
    private Connection $connection;

    private EntityRepositoryInterface $customerRepository;

    /**
     * @internal
     */
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

    /**
     *  @deprecated tag:v6.5.0 Will be removed
     */
    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('v6.5.0.0')) {
            return [];
        }

        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return [
            self::getName() => 'handle',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function requirements(): array
    {
        return [CustomerAware::class, DelayAware::class];
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed, implement handleFlow instead
     */
    public function handle(FlowEvent $event): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $baseEvent = $event->getEvent();

        if (!$baseEvent instanceof CustomerAware) {
            return;
        }

        $this->update($baseEvent->getContext(), $event->getConfig(), $baseEvent->getCustomerId());
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasStore(CustomerAware::CUSTOMER_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getStore(CustomerAware::CUSTOMER_ID));
    }

    /**
     * @return array<mixed>
     */
    private function getAffiliateAndCampaignCodeFromCustomerId(string $customerId): array
    {
        $data = $this->connection->fetchAssociative(
            'SELECT affiliate_code, campaign_code FROM customer WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($customerId),
            ]
        );

        if (!$data) {
            return [];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $customerId): void
    {
        if (!\array_key_exists('affiliateCode', $config) || !\array_key_exists('campaignCode', $config)) {
            return;
        }

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

        $this->customerRepository->update([$data], $context);
    }
}
