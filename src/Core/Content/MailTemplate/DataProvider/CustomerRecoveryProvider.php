<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\DataProvider;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Content\Shared\MailFlow\CustomerRecoveryCriteriaBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class CustomerRecoveryProvider implements DataProvider
{
    /**
     * @param EntityRepository<CustomerRecoveryCollection> $customerRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRecoveryRepository,
        private readonly CustomerRecoveryCriteriaBuilder $customerRecoveryCriteriaBuilder,
    ) {
    }

    public function getData(string $entityId, Context $context): ?CustomerRecoveryEntity
    {
        $criteria = $this->customerRecoveryCriteriaBuilder->getCriteria($entityId, $context);

        return $this->customerRecoveryRepository->search($criteria, $context)->getEntities()->get($entityId);
    }
}
