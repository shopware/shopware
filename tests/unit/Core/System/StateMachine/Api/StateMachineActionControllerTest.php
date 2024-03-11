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
use Shopware\Core\System\StateMachine\Api\StateMachineActionController;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
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
        $this->expectException(MissingPrivilegeException::class);
        $this->expectExceptionMessage('{"message":"Missing privilege","missingPrivileges":["order:update"]}');

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
        $this->expectException(MissingPrivilegeException::class);
        $this->expectExceptionMessage('{"message":"Missing privilege","missingPrivileges":["order:read"]}');

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
}
