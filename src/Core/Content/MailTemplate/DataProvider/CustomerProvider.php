<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\DataProvider;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Mail\Event\BeforeLoadMailDataProviderEvent;
use Shopware\Core\Content\Shared\MailFlow\CustomerCriteriaBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class CustomerProvider implements DataProvider
{
    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly CustomerCriteriaBuilder $customerCriteriaBuilder,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function getData(string $entityId, Context $context): ?CustomerEntity
    {
        $criteria = $this->customerCriteriaBuilder->getCriteria($entityId);

        $event = new BeforeLoadMailDataProviderEvent(
            CustomerDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        return $this->customerRepository->search($criteria, $context)->getEntities()->get($entityId);
    }
}
