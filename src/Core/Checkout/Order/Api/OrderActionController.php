<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Api;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderActionController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var StateMachineRegistry
     */
    protected $stateMachineRegistry;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->orderRepository = $orderRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @Route("/api/v{version}/_action/order/{orderId}/state", name="api.action.order.get-state", methods={"GET"})
     *
     * @throws ResourceNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     */
    public function getAvailableTransitions(Request $request, Context $context, string $orderId): Response
    {
        $order = $this->getOrder($orderId, $context);

        $baseUrl = $this->generateUrl('api.action.order.transition_state', [
            'orderId' => $order->getId(),
            'version' => $request->get('version'),
        ]);

        return $this->stateMachineRegistry->buildAvailableTransitionsJsonResponse(
            OrderStates::STATE_MACHINE,
            $order->getStateMachineState()->getTechnicalName(),
            $baseUrl,
            $context
        );
    }

    /**
     * @Route("/api/v{version}/_action/order/{orderId}/state/{transition?}", name="api.action.order.transition_state", methods={"POST"})
     *
     * @throws InconsistentCriteriaIdsException
     * @throws ResourceNotFoundException
     * @throws StateMachineNotFoundException
     * @throws IllegalTransitionException
     */
    public function transitionOrderState(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $orderId,
        ?string $transition = null
    ): Response {
        $order = $this->getOrder($orderId, $context);

        $toPlace = $this->stateMachineRegistry->transition(
            $this->stateMachineRegistry->getStateMachine(OrderStates::STATE_MACHINE, $context),
            $order->getStateMachineState(),
            $this->orderRepository->getDefinition()->getEntityName(),
            $order->getId(),
            $context,
            $transition
        );

        $order->setStateMachineState($toPlace);
        $order->setStateId($toPlace->getId());

        return $responseFactory->createDetailResponse($order, $this->orderRepository->getDefinition(), $request, $context);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws ResourceNotFoundException
     */
    private function getOrder(string $id, Context $context): OrderEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('stateMachineState');

        $result = $this->orderRepository->search($criteria, $context);

        if ($result->count() === 0) {
            throw new ResourceNotFoundException($this->orderRepository->getDefinition()->getEntityName(), ['id' => $id]);
        }

        return $result->first();
    }
}
