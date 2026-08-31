<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Controller\UserController;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserController::class)]
class UserControllerTest extends TestCase
{
    public function testUpdateMeAllowsChangingTimezone(): void
    {
        $userId = 'test-user-id';
        $context = Context::createDefaultContext(new AdminApiSource($userId));
        $request = Request::create('/', Request::METHOD_PATCH, ['timeZone' => 'Europe/Berlin']);
        $userDefinition = new UserDefinition();
        $userRepository = new StaticEntityRepository([], $userDefinition);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $responseFactory->expects($this->once())
            ->method('createRedirectResponse')
            ->with($userDefinition, $userId, $request, $context)
            ->willReturn($response);

        $controller = $this->createController(userRepository: $userRepository, userDefinition: $userDefinition);

        static::assertSame(Response::HTTP_NO_CONTENT, $controller->updateMe($context, $request, $responseFactory)->getStatusCode());
    }

    private function createController(?EntityRepository $userRepository = null, ?UserDefinition $userDefinition = null): UserController
    {
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $func) => $func($connection));

        return new UserController(
            $userRepository ?? static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $userDefinition ?? static::createStub(UserDefinition::class),
            $connection,
        );
    }
}
