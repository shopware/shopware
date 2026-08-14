<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\Api\Controller\UserController;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoService;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserController::class)]
class UserControllerTest extends TestCase
{
    public function testLogoutRevokesTokensAndReturnsNoContent(): void
    {
        $userId = 'test-user-id';

        $ssoService = $this->createMock(SsoService::class);
        $ssoService->expects($this->once())
            ->method('revokeUserTokens')
            ->with($userId);

        $controller = $this->createController($ssoService);
        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $response = $controller->logout($context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testLogoutThrowsForNonAdminApiSource(): void
    {
        static::expectExceptionObject(ApiException::invalidAdminSource(ShopApiSource::class));

        $controller = $this->createController();
        $context = Context::createDefaultContext(new ShopApiSource('test-channel'));

        $controller->logout($context);
    }

    public function testLogoutThrowsWhenUserIdIsNull(): void
    {
        static::expectExceptionObject(ApiException::userNotLoggedIn());

        $controller = $this->createController();
        $context = Context::createDefaultContext(new AdminApiSource(null));

        $controller->logout($context);
    }

    public function testUpdateMeAllowsChangingTimezone(): void
    {
        $userId = 'test-user-id';
        $context = Context::createDefaultContext(new AdminApiSource($userId));
        $request = Request::create('/', Request::METHOD_PATCH, ['timeZone' => 'Europe/Berlin']);
        $responseFactory = static::createStub(ResponseFactoryInterface::class);
        $response = new Response();

        $controller = $this->getMockBuilder(UserController::class)
            ->setConstructorArgs([
                static::createStub(EntityRepository::class),
                static::createStub(EntityRepository::class),
                static::createStub(EntityRepository::class),
                static::createStub(EntityRepository::class),
                static::createStub(UserDefinition::class),
                static::createStub(SsoService::class),
            ])
            ->onlyMethods(['upsertUser'])
            ->getMock();
        $controller->expects($this->once())
            ->method('upsertUser')
            ->with($userId, $request, $context, $responseFactory)
            ->willReturn($response);

        static::assertSame($response, $controller->updateMe($context, $request, $responseFactory));
    }

    public function testUpdateMeRejectsFieldsOutsideTheSelfProfileAllowlist(): void
    {
        static::expectExceptionObject(ApiException::missingPrivileges(['user:update']));

        $controller = $this->createController();
        $context = Context::createDefaultContext(new AdminApiSource('test-user-id'));
        $request = Request::create('/', Request::METHOD_PATCH, ['title' => 'Dr.']);

        $controller->updateMe($context, $request, static::createStub(ResponseFactoryInterface::class));
    }

    private function createController(?SsoService $ssoService = null): UserController
    {
        return new UserController(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(UserDefinition::class),
            $ssoService ?? static::createStub(SsoService::class),
        );
    }
}
