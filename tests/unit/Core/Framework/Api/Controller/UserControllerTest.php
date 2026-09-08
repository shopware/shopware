<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Controller\UserController;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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

    /**
     * updateMe() must reject any field outside the self-service profile allow-list before the
     * SYSTEM_SCOPE write, including escalation-relevant ones such as `admin` and `aclRoles`.
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('forbiddenUpdateMePayloadProvider')]
    public function testUpdateMeRejectsFieldsOutsideProfileAllowList(array $payload): void
    {
        static::expectExceptionObject(ApiException::missingPrivileges(['user:update']));

        $context = Context::createDefaultContext(new AdminApiSource('user-id'));
        $request = Request::create('/', Request::METHOD_PATCH, $payload);

        $this->createController()->updateMe($context, $request, $this->createMock(ResponseFactoryInterface::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function forbiddenUpdateMePayloadProvider(): iterable
    {
        yield 'disallowed top-level admin flag' => [[
            'admin' => true,
        ]];

        yield 'disallowed acl role assignment' => [[
            'aclRoles' => [['id' => 'role-id']],
        ]];

        yield 'field outside the profile allow-list' => [[
            'title' => 'Dr.',
        ]];
    }

    public function testUpdateMeAllowsLinkingAnAvatarMedia(): void
    {
        $userId = 'test-user-id';
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext(new AdminApiSource($userId));
        $request = Request::create('/', Request::METHOD_PATCH, ['avatarMedia' => ['id' => $mediaId]]);
        $userDefinition = new UserDefinition();
        $userRepository = new StaticEntityRepository([], $userDefinition);
        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects($this->once())
            ->method('createRedirectResponse')
            ->willReturn(new Response(null, Response::HTTP_NO_CONTENT));

        $controller = $this->createController(userRepository: $userRepository, userDefinition: $userDefinition);
        $controller->updateMe($context, $request, $responseFactory);

        static::assertSame(['id' => $mediaId], $userRepository->upserts[0][0]['avatarMedia']);
    }

    /**
     * A self-service profile edit may link an avatar, nothing more. Any other media field would be
     * written in SYSTEM_SCOPE, so updateMe() must reject it before the write.
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('forbiddenAvatarMediaPayloadProvider')]
    public function testUpdateMeRejectsAvatarMediaBeyondAnIdLink(array $payload): void
    {
        static::expectExceptionObject(ApiException::missingPrivileges(['user:update']));

        $context = Context::createDefaultContext(new AdminApiSource('user-id'));
        $request = Request::create('/', Request::METHOD_PATCH, $payload);

        $this->createController()->updateMe($context, $request, $this->createMock(ResponseFactoryInterface::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function forbiddenAvatarMediaPayloadProvider(): iterable
    {
        yield 'nested user association' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'user' => ['id' => Uuid::randomHex(), 'admin' => true]],
        ]];

        yield 'nested avatarUsers association' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'avatarUsers' => [['id' => Uuid::randomHex(), 'admin' => true]]],
        ]];

        yield 'association hidden in the extensions container' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'extensions' => ['user' => ['id' => Uuid::randomHex(), 'admin' => true]]],
        ]];

        yield 'scalar field of the media entity' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'private' => true],
        ]];

        yield 'link without an id' => [[
            'avatarMedia' => ['fileName' => 'renamed'],
        ]];

        yield 'empty link' => [[
            'avatarMedia' => [],
        ]];

        yield 'non string id' => [[
            'avatarMedia' => ['id' => ['nested']],
        ]];

        yield 'plain id instead of a link' => [[
            'avatarMedia' => Uuid::randomHex(),
        ]];

        yield 'null' => [[
            'avatarMedia' => null,
        ]];
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
