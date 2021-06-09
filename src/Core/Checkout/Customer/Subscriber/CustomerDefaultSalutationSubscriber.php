<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerDefaultSalutationSubscriber implements EventSubscriberInterface
{
    private EntityRepositoryInterface $salutationRepository;

    private ?SalutationEntity $defaultSalutation = null;

    public function __construct(EntityRepositoryInterface $salutationRepository)
    {
        $this->salutationRepository = $salutationRepository;
    }

    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('FEATURE_NEXT_7739')) {
            return [];
        }

        return [
            CustomerEvents::CUSTOMER_LOADED_EVENT => [
                ['loaded'],
            ],
            CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT => [
                ['loaded'],
            ],
            OrderEvents::ORDER_ADDRESS_LOADED_EVENT => [
                ['loaded'],
            ],
        ];
    }

    public function loaded(EntityLoadedEvent $event): void
    {
        /** @var CustomerEntity|CustomerAddressEntity|OrderAddressEntity $entity */
        foreach ($event->getEntities() as $entity) {
            if ($entity->getSalutation() === null) {
                $entity->setSalutation($this->getDefaultSalutation($event->getContext()));
            }

            if ($entity->getSalutationId() === null) {
                $entity->setSalutationId($this->getDefaultSalutation($event->getContext())->getId());
            }
        }
    }

    private function getDefaultSalutation(Context $context): SalutationEntity
    {
        if ($this->defaultSalutation !== null) {
            return $this->defaultSalutation;
        }

        $this->defaultSalutation = $this->salutationRepository->search(
            new Criteria([Defaults::SALUTATION]),
            $context
        )->first();

        return $this->defaultSalutation;
    }
}
