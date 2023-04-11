<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[Package('customer-order')]
class DeleteUnusedGuestCustomerService
{
    final public const DELETE_CUSTOMERS_BATCH_SIZE = 100;

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function countUnusedCustomers(Context $context): int
    {
        $maxLifeTime = $this->getUnusedGuestCustomerLifeTime();

        if (!$maxLifeTime) {
            return 0;
        }

        $criteria = $this->getUnusedCustomerCriteria($maxLifeTime);

        $criteria
            ->setLimit(1)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $this->customerRepository->search($criteria, $context)->getTotal();
    }

    /**
     * @return list<array{id: string}>
     */
    public function deleteUnusedCustomers(Context $context): array
    {
        $maxLifeTime = $this->getUnusedGuestCustomerLifeTime();

        if (!$maxLifeTime) {
            return [];
        }

        $criteria = $this->getUnusedCustomerCriteria($maxLifeTime);
        $criteria->setLimit(self::DELETE_CUSTOMERS_BATCH_SIZE);

        /** @var list<string> $ids */
        $ids = $this->customerRepository->searchIds($criteria, $context)->getIds();
        $ids = \array_values(\array_map(static fn (string $id) => ['id' => $id], $ids));

        $this->customerRepository->delete($ids, $context);

        return $ids;
    }

    private function getUnusedCustomerCriteria(\DateTime $maxLifeTime): Criteria
    {
        $criteria = new Criteria();

        $criteria->addAssociation('orderCustomers');

        $criteria->addFilter(
            new AndFilter(
                [
                    new EqualsFilter('guest', true),
                    new EqualsFilter('orderCustomers.id', null),
                    new RangeFilter(
                        'createdAt',
                        [
                            RangeFilter::LTE => $maxLifeTime->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        ]
                    ),
                ]
            )
        );

        return $criteria;
    }

    private function getUnusedGuestCustomerLifeTime(): ?\DateTime
    {
        $maxLifeTime = $this->systemConfigService->getInt(
            'core.loginRegistration.unusedGuestCustomerLifetime'
        );

        if ($maxLifeTime <= 0) {
            return null;
        }

        return new \DateTime(\sprintf('- %d seconds', $maxLifeTime));
    }
}
