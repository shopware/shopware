<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Api;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderDeliveryActionController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $orderDeliveryRepository;

    /**
     * @var StateMachineRegistry
     */
    protected $stateMachineRegistry;

    public function __construct(
        EntityRepositoryInterface $orderDeliveryRepository,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @Route("/api/v{version}/_action/order-delivery/{deliveryId}/state", name="api.action.order_delivery.state", methods={"GET"})
     *
     * @throws StateMachineNotFoundException
     * @throws ResourceNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function getAvailableTransitions(Request $request, Context $context, string $deliveryId): Response
    {
        $delivery = $this->getOrderDelivery($deliveryId, $context);

        $baseUrl = $this->generateUrl('api.action.order_delivery.transition_state', [
            'deliveryId' => $delivery->getId(),
            'version' => $request->get('version'),
        ]);

        return $this->stateMachineRegistry->buildAvailableTransitionsJsonResponse(OrderDeliveryStates::STATE_MACHINE,
            $delivery->getStateMachineState()->getTechnicalName(),
            $baseUrl,
            $context);
    }

    /**
     * @Route("/api/v{version}/_action/order-delivery/{deliveryId}/state/{transition?}", name="api.action.order_delivery.transition_state", methods={"POST"})
     *
     * @throws StateMachineNotFoundException
     * @throws IllegalTransitionException
     * @throws ResourceNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function transitionOrderState(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $deliveryId,
        ?string $transition = null
    ): Response {
        $delivery = $this->getOrderDelivery($deliveryId, $context);

        $stateMachine = $this->stateMachineRegistry->getStateMachine(OrderTransactionStates::STATE_MACHINE, $context);

        $toPlace = $this->stateMachineRegistry->transition(
            $stateMachine,
            $delivery->getStateMachineState(),
            $this->orderDeliveryRepository->getDefinition()->getEntityName(),
            $delivery->getId(),
            $context,
            $transition
        );

        $payload = [
            ['id' => $delivery->getId(), 'stateId' => $toPlace->getId()],
        ];

        $this->orderDeliveryRepository->update($payload, $context);

        $delivery->setStateMachineState($toPlace);
        $delivery->setStateId($toPlace->getId());

        return $responseFactory->createDetailResponse($delivery, $this->orderDeliveryRepository->getDefinition(), $request, $context);
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrderDelivery(string $id, Context $context): OrderDeliveryEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('stateMachineState');

        $result = $this->orderDeliveryRepository->search($criteria, $context);

        if ($result->count() === 0) {
            throw new ResourceNotFoundException($this->orderDeliveryRepository->getDefinition()->getEntityName(), ['id' => $id]);
        }

        return $result->first();
    }
}
