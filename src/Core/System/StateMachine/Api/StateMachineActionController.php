<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Api;

use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class StateMachineActionController extends AbstractController
{
    /**
     * @var StateMachineRegistry
     */
    protected $stateMachineRegistry;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;

    public function __construct(
        StateMachineRegistry $stateMachineRegistry,
        DefinitionInstanceRegistry $definitionInstanceRegistry
    ) {
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/state-machine/{entityName}/{entityId}/state", name="api.state_machine.states", methods={"GET"})
     *
     * @throws InconsistentCriteriaIdsException
     * @throws ResourceNotFoundException
     * @throws StateMachineNotFoundException
     */
    public function getAvailableTransitions(
        Request $request,
        Context $context,
        string $entityName,
        string $entityId
    ): JsonResponse {
        $stateFieldName = (string) $request->query->get('stateFieldName', 'stateId');

        $availableTransitions = $this->stateMachineRegistry->getAvailableTransitions(
            $entityName,
            $entityId,
            $stateFieldName,
            $context
        );

        $transitionsJson = [];
        /** @var StateMachineTransitionEntity $transition */
        foreach ($availableTransitions as $transition) {
            $transitionsJson[] = [
                'name' => $transition->getToStateMachineState()->getName(),
                'technicalName' => $transition->getToStateMachineState()->getTechnicalName(),
                'actionName' => $transition->getActionName(),
                'fromStateName' => $transition->getFromStateMachineState()->getTechnicalName(),
                'toStateName' => $transition->getToStateMachineState()->getTechnicalName(),
                'url' => $this->generateUrl('api.state_machine.transition_state', [
                    'entityName' => $entityName,
                    'entityId' => $entityId,
                    'version' => $request->attributes->get('version'),
                    'transition' => $transition->getActionName(),
                ]),
            ];
        }

        return new JsonResponse([
            'transitions' => $transitionsJson,
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/state-machine/{entityName}/{entityId}/state/{transition}", name="api.state_machine.transition_state", methods={"POST"})
     *
     * @throws IllegalTransitionException
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws DefinitionNotFoundException
     * @throws StateMachineInvalidEntityIdException
     * @throws StateMachineInvalidStateFieldException
     */
    public function transitionState(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $entityName,
        string $entityId,
        string $transition
    ): Response {
        $stateFieldName = (string) $request->query->get('stateFieldName', 'stateId');
        $stateMachineStateCollection = $this->stateMachineRegistry->transition(
            new Transition(
                $entityName,
                $entityId,
                $transition,
                $stateFieldName
            ),
            $context
        );

        return $responseFactory->createDetailResponse(
            new Criteria(),
            $stateMachineStateCollection->get('toPlace'),
            $this->definitionInstanceRegistry->get(StateMachineStateDefinition::class),
            $request,
            $context
        );
    }
}
