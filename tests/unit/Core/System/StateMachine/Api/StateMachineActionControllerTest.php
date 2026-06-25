<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\StateMachine\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Api\StateMachineActionController;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StateMachineActionController::class)]
class StateMachineActionControllerTest extends TestCase
{
    public function testTransitionWithoutPrivileges(): void
    {
        $this->expectExceptionObject(new MissingPrivilegeException(['order:update']));

        $controller = new StateMachineActionController(
            $this->createMock(StateMachineRegistry::class),
            $this->createMock(DefinitionInstanceRegistry::class),
        );
        $controller->transitionState(
            new Request(),
            Context::createDefaultContext(new AdminApiSource(null)),
            $this->createMock(ResponseFactoryInterface::class),
            'order',
            '1234',
            'process',
        );
    }

    public function testGetAvailableTransitionsWithoutPrivileges(): void
    {
        $this->expectExceptionObject(new MissingPrivilegeException(['order:read']));

        $controller = new StateMachineActionController(
            $this->createMock(StateMachineRegistry::class),
            $this->createMock(DefinitionInstanceRegistry::class),
        );
        $controller->getAvailableTransitions(
            new Request(),
            Context::createDefaultContext(new AdminApiSource(null)),
            'order',
            '1234',
        );
    }

    public function testTransitionUseData(): void
    {
        $stateMachineRegistry = $this->createMock(StateMachineRegistry::class);

        $source = new AdminApiSource(null);
        $source->setPermissions(['order:update']);
        $context = Context::createDefaultContext($source);

        $stateMachineStates = new StateMachineStateCollection();
        $toPlace = new StateMachineStateEntity();
        $stateMachineStates->set('toPlace', $toPlace);

        $expectedTransition = new Transition('order', '1234', 'process', 'abc', 'def');

        $stateMachineRegistry->expects($this->once())
            ->method('transition')
            ->with(
                static::equalTo($expectedTransition),
                $context
            )
            ->willReturn($stateMachineStates);

        $controller = new StateMachineActionController(
            $stateMachineRegistry,
            $this->createMock(DefinitionInstanceRegistry::class),
        );

        $request = new Request(query: ['stateFieldName' => 'abc'], request: ['internalComment' => 'def']);

        $controller->transitionState(
            $request,
            $context,
            $this->createMock(ResponseFactoryInterface::class),
            'order',
            '1234',
            'process',
        );
    }
}
