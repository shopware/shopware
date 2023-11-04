<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Controller\Exception\ExpectedUserHttpException;
use Shopware\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('system-settings')]
class UserController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $userRoleRepository,
        private readonly EntityRepository $roleRepository,
        private readonly EntityRepository $keyRepository,
        private readonly UserDefinition $userDefinition
    ) {
    }

    #[Route(path: '/api/_info/me', name: 'api.info.me', methods: ['GET'])]
    public function me(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, $context->getSource()::class);
        }

        $userId = $context->getSource()->getUserId();
        if (!$userId) {
            throw new ExpectedUserHttpException();
        }
        $criteria = new Criteria([$userId]);
        $criteria->addAssociation('aclRoles');

        $user = $this->userRepository->search($criteria, $context)->first();
        if (!$user) {
            throw OAuthServerException::invalidCredentials();
        }

        return $responseFactory->createDetailResponse(new Criteria(), $user, $this->userDefinition, $request, $context);
    }

    #[Route(path: '/api/_info/me', name: 'api.change.me', defaults: ['auth_required' => true, '_acl' => ['user_change_me']], methods: ['PATCH'])]
    public function updateMe(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, $context->getSource()::class);
        }

        $userId = $context->getSource()->getUserId();
        if (!$userId) {
            throw new ExpectedUserHttpException();
        }

        $allowedChanges = ['firstName', 'lastName', 'username', 'localeId', 'email', 'avatarMedia', 'avatarId', 'password'];

        if (!empty(array_diff(array_keys($request->request->all()), $allowedChanges))) {
            throw new MissingPrivilegeException(['user:update']);
        }

        return $this->upsertUser($userId, $request, $context, $responseFactory);
    }

    #[Route(path: '/api/_info/ping', name: 'api.info.ping', methods: ['GET'])]
    public function status(Context $context): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, $context->getSource()::class);
        }

        $userId = $context->getSource()->getUserId();
        if (!$userId) {
            throw new ExpectedUserHttpException();
        }
        $result = $this->userRepository->searchIds(new Criteria([$userId]), $context);

        if ($result->getTotal() === 0) {
            throw OAuthServerException::invalidCredentials();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/user/{userId}', name: 'api.user.delete', defaults: ['auth_required' => true, '_acl' => ['user:delete']], methods: ['DELETE'])]
    public function deleteUser(string $userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        if (!$this->hasScope($request, UserVerifiedScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException(sprintf('This access token does not have the scope "%s" to process this Request', UserVerifiedScope::IDENTIFIER));
        }

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        if (
            !$source->isAllowed('user:update')
            && $source->getUserId() !== $userId
        ) {
            throw new PermissionDeniedException();
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId): void {
            $this->userRepository->delete([['id' => $userId]], $context);
        });

        return $factory->createRedirectResponse($this->userRepository->getDefinition(), $userId, $request, $context);
    }

    #[Route(path: '/api/user/{userId}/access-keys/{id}', name: 'api.user_access_keys.delete', defaults: ['auth_required' => true, '_acl' => ['user_access_key:delete']], methods: ['DELETE'])]
    public function deleteUserAccessKey(string $id, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        if (!$this->hasScope($request, UserVerifiedScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException(sprintf('This access token does not have the scope "%s" to process this Request', UserVerifiedScope::IDENTIFIER));
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id): void {
            $this->keyRepository->delete([['id' => $id]], $context);
        });

        return $factory->createRedirectResponse($this->keyRepository->getDefinition(), $id, $request, $context);
    }

    #[Route(path: '/api/user', name: 'api.user.create', defaults: ['auth_required' => true, '_acl' => ['user:create']], methods: ['POST'])]
    public function upsertUser(?string $userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        if (!$this->hasScope($request, UserVerifiedScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException(sprintf('This access token does not have the scope "%s" to process this Request', UserVerifiedScope::IDENTIFIER));
        }

        $data = $request->request->all();

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        if (!isset($data['id'])) {
            $data['id'] = null;
        }
        $data['id'] = $userId ?: $data['id'];

        if (
            !$source->isAllowed('user:update')
            && $source->getUserId() !== $data['id']
        ) {
            throw new PermissionDeniedException();
        }

        $events = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->userRepository->upsert([$data], $context));

        $event = $events->getEventByEntityName(UserDefinition::ENTITY_NAME);

        $eventIds = $event->getIds();
        $entityId = array_pop($eventIds);

        return $factory->createRedirectResponse($this->userRepository->getDefinition(), $entityId, $request, $context);
    }

    #[Route(path: '/api/user/{userId}', name: 'api.user.update', defaults: ['auth_required' => true, '_acl' => ['user:update']], methods: ['PATCH'])]
    public function updateUser(?string $userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        return $this->upsertUser($userId, $request, $context, $factory);
    }

    #[Route(path: '/api/acl-role', name: 'api.acl_role.create', defaults: ['auth_required' => true, '_acl' => ['acl_role:create']], methods: ['POST'])]
    public function upsertRole(?string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        if (!$this->hasScope($request, UserVerifiedScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException(sprintf('This access token does not have the scope "%s" to process this Request', UserVerifiedScope::IDENTIFIER));
        }

        $data = $request->request->all();

        if (!isset($data['id'])) {
            $data['id'] = $roleId ?? null;
        }

        $events = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->roleRepository->upsert([$data], $context));

        $event = $events->getEventByEntityName(AclRoleDefinition::ENTITY_NAME);

        $eventIds = $event->getIds();
        $entityId = array_pop($eventIds);

        return $factory->createRedirectResponse($this->roleRepository->getDefinition(), $entityId, $request, $context);
    }

    #[Route(path: '/api/acl-role/{roleId}', name: 'api.acl_role.update', defaults: ['auth_required' => true, '_acl' => ['acl_role:update']], methods: ['PATCH'])]
    public function updateRole(?string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        return $this->upsertRole($roleId, $request, $context, $factory);
    }

    #[Route(path: '/api/user/{userId}/acl-roles/{roleId}', name: 'api.user_role.delete', defaults: ['auth_required' => true, '_acl' => ['acl_user_role:delete']], methods: ['DELETE'])]
    public function deleteUserRole(string $userId, string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        if (!$this->hasScope($request, UserVerifiedScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException(sprintf('This access token does not have the scope "%s" to process this Request', UserVerifiedScope::IDENTIFIER));
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($roleId, $userId): void {
            $this->userRoleRepository->delete([['userId' => $userId, 'aclRoleId' => $roleId]], $context);
        });

        return $factory->createRedirectResponse($this->userRoleRepository->getDefinition(), $roleId, $request, $context);
    }

    #[Route(path: '/api/acl-role/{roleId}', name: 'api.acl_role.delete', defaults: ['auth_required' => true, '_acl' => ['acl_role:delete']], methods: ['DELETE'])]
    public function deleteRole(string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        if (!$this->hasScope($request, UserVerifiedScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException(sprintf('This access token does not have the scope "%s" to process this Request', UserVerifiedScope::IDENTIFIER));
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($roleId): void {
            $this->roleRepository->delete([['id' => $roleId]], $context);
        });

        return $factory->createRedirectResponse($this->roleRepository->getDefinition(), $roleId, $request, $context);
    }

    private function hasScope(Request $request, string $scopeIdentifier): bool
    {
        $scopes = array_flip($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES));

        return isset($scopes[$scopeIdentifier]);
    }
}
