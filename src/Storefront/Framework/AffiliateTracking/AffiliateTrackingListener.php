<?php

namespace Shopware\Storefront\Framework\AffiliateTracking;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AffiliateTrackingListener implements EventSubscriberInterface
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        SessionInterface $session,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $customerRepository
    )
    {
        $this->session = $session;
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [['checkAffiliateTracking', -128]],
            OrderEvents::ORDER_WRITTEN_EVENT => [['addOrderTrackingCodes']],
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => [['addCustomerTrackingCodes']]
        ];
    }

    public function addCustomerTrackingCodes(EntityWrittenEvent $event)
    {
        $this->addTrackingCodes($event, $this->customerRepository, new CustomerDefinition());
    }

    public function addOrderTrackingCodes(EntityWrittenEvent $event)
    {
        $this->addTrackingCodes($event, $this->orderRepository, new OrderDefinition());
    }

    public function checkAffiliateTracking(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        /** @var RouteScope|null $routeScope */
        $routeScope = $request->attributes->get('_routeScope');

        // Only process storefront routes
        if ($routeScope && !in_array('storefront', $routeScope->getScopes(), true)) {
            return;
        }

        $session = $request->getSession();
        $affiliateCode = $request->query->get('affiliateCode');
        $campaignCode = $request->query->get('campaignCode');

        if ($affiliateCode && $campaignCode) {
            $session->set('affiliateCode', $affiliateCode);
            $session->set('campaignCode', $campaignCode);
        }
    }

    private function addTrackingCodes(
        EntityWrittenEvent $event,
        EntityRepositoryInterface $repository,
        EntityDefinition $entityDefinition
    )
    {
        $affiliateCode =  $this->session->get('affiliateCode');
        $campaignCode = $this->session->get('campaignCode');

        if (!$affiliateCode || !$campaignCode) {
            return;
        }

        if ($event->getEntityName() !== $entityDefinition::ENTITY_NAME) {
            return;
        }

        $context = $event->getContext();

        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getExistence() === null || $writeResult->getExistence()->exists()) {
                break;
            }

            $payload = $writeResult->getPayload();
            if (empty($payload)) {
                continue;
            }

            /** @var EntitySearchResult $result */
            $result = $repository->search(
                (new Criteria([$payload['id']])),
                $context
            );

            /** @var Entity $entity */
            $entity = $result->first();

            $entityClass = $entityDefinition->getEntityClass();
            if (!($entity instanceof $entityClass)) {
                continue;
            }

            $repository->update([[
                'id' => $entity->getId(),
                'affiliateCode' => $affiliateCode,
                'campaignCode' => $campaignCode
            ]], $context);
        }
    }
}
