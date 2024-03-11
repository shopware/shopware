<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Api;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('checkout')]
class StateMachineActionController extends AbstractController
{
    /**
     * @var StateMachineRegistry
     *
     * @deprecated tag:v6.7.0 - will be private and typed
     */
    protected $stateMachineRegistry;

    /**
     * @internal
     */
    public function __construct(
        StateMachineRegistry $stateMachineRegistry,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry
    ) {
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    #[Route(path: '/api/_action/state-machine/{entityName}/{entityId}/state', name: 'api.state_machine.states', methods: ['GET'])]
    public function getAvailableTransitions(
        Request $request,
        Context $context,
        string $entityName,
        string $entityId
    ): JsonResponse {
        $this->validatePrivilege($entityName, AclRoleDefinition::PRIVILEGE_READ, $context);
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
                'name' => $transition->getToStateMachineState()?->getName(),
                'technicalName' => $transition->getToStateMachineState()?->getTechnicalName(),
                'actionName' => $transition->getActionName(),
                'fromStateName' => $transition->getFromStateMachineState()?->getTechnicalName(),
                'toStateName' => $transition->getToStateMachineState()?->getTechnicalName(),
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

    #[Route(path: '/api/_action/state-machine/{entityName}/{entityId}/state/{transition}', name: 'api.state_machine.transition_state', methods: ['POST'])]
    public function transitionState(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $entityName,
        string $entityId,
        string $transition
    ): Response {
        $this->validatePrivilege($entityName, AclRoleDefinition::PRIVILEGE_UPDATE, $context);

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

        $toPlace = $stateMachineStateCollection->get('toPlace');
        if ($toPlace === null) {
            throw StateMachineException::stateMachineStateNotFound($entityName, $transition);
        }

        return $responseFactory->createDetailResponse(
            new Criteria(),
            $toPlace,
            $this->definitionInstanceRegistry->get(StateMachineStateDefinition::class),
            $request,
            $context
        );
    }

    private function validatePrivilege(string $entityName, string $privilege, Context $context): void
    {
        $permission = $entityName . ':' . $privilege;
        if (!$context->isAllowed($permission)) {
            throw StateMachineException::missingPrivileges([$permission]);
        }
    }
}
