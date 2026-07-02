<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\Api\Controller\UserController;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\Test\Annotation\DisabledFeatures;
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

        $refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $refreshTokenRepository->expects($this->once())
            ->method('revokeRefreshTokensForUser')
            ->with($userId);

        $controller = $this->createController($refreshTokenRepository);
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

    public function testAdministrationClientWithoutUserVerifiedScopeIsRejected(): void
    {
        static::expectExceptionObject(ApiException::invalidScopeAccessToken(UserVerifiedScope::IDENTIFIER));

        $this->createController()->deleteUserAccessKey(
            'key-id',
            $this->createAdministrationRequest(scopes: []),
            Context::createDefaultContext(new AdminApiSource('test-user-id')),
            $this->createMock(ResponseFactoryInterface::class)
        );
    }

    public function testAdministrationClientWithUserVerifiedScopeIsAllowed(): void
    {
        $response = $this->createController()->deleteUserAccessKey(
            'key-id',
            $this->createAdministrationRequest(scopes: [UserVerifiedScope::IDENTIFIER]),
            Context::createDefaultContext(new AdminApiSource('test-user-id')),
            $this->createResponseFactory()
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testUserVerifiedScopeIsNotRequiredWhenPasswordLoginIsDisabled(): void
    {
        // Without password login users cannot re-verify via password, so the scope is unobtainable.
        $response = $this->createController(passwordLoginEnabled: false)->deleteUserAccessKey(
            'key-id',
            $this->createAdministrationRequest(scopes: []),
            Context::createDefaultContext(new AdminApiSource('test-user-id')),
            $this->createResponseFactory()
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    #[DisabledFeatures(['ADMIN_AUTH'])]
    public function testUserVerifiedScopeIsStillRequiredWhenTheFeatureIsInactive(): void
    {
        static::expectExceptionObject(ApiException::invalidScopeAccessToken(UserVerifiedScope::IDENTIFIER));

        $this->createController(passwordLoginEnabled: false)->deleteUserAccessKey(
            'key-id',
            $this->createAdministrationRequest(scopes: []),
            Context::createDefaultContext(new AdminApiSource('test-user-id')),
            $this->createMock(ResponseFactoryInterface::class)
        );
    }

    private function createController(
        ?RefreshTokenRepository $refreshTokenRepository = null,
        bool $passwordLoginEnabled = true,
    ): UserController {
        return new UserController(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(UserDefinition::class),
            $refreshTokenRepository ?? $this->createMock(RefreshTokenRepository::class),
            $passwordLoginEnabled,
        );
    }

    /**
     * @param list<string> $scopes
     */
    private function createAdministrationRequest(array $scopes): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, 'administration');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, $scopes);

        return $request;
    }

    private function createResponseFactory(): ResponseFactoryInterface
    {
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $factory->method('createRedirectResponse')->willReturn(new Response(null, Response::HTTP_NO_CONTENT));

        return $factory;
    }
}
