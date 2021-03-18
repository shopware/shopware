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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class UserController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $userRoleRepository;

    /**
     * @var UserDefinition
     */
    private $userDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $keyRepository;

    public function __construct(
        EntityRepositoryInterface $userRepository,
        EntityRepositoryInterface $userRoleRepository,
        EntityRepositoryInterface $roleRepository,
        EntityRepositoryInterface $keyRepository,
        UserDefinition $userDefinition
    ) {
        $this->userRepository = $userRepository;
        $this->roleRepository = $roleRepository;
        $this->userDefinition = $userDefinition;
        $this->keyRepository = $keyRepository;
        $this->userRoleRepository = $userRoleRepository;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_info/me", name="api.info.me", methods={"GET"})
     */
    public function me(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
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

    /**
     * @Since("6.3.3.0")
     * @Route("/api/_info/me", name="api.change.me", defaults={"auth_required"=true}, methods={"PATCH"})
     * @Acl({"user_change_me"})
     */
    public function updateMe(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
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

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_info/ping", name="api.info.ping", methods={"GET"})
     */
    public function status(Context $context): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
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

    /**
     * @Since("6.2.3.0")
     * @Route("/api/user/{userId}", name="api.user.delete", defaults={"auth_required"=true}, methods={"DELETE"})
     * @Acl({"user:delete"})
     */
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

    /**
     * @Since("6.3.0.0")
     * @Route("/api/user/{userId}/access-keys/{id}", name="api.user_access_keys.delete", defaults={"auth_required"=true}, methods={"DELETE"})
     * @Acl({"user_access_key:delete"})
     */
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

    /**
     * @Since("6.2.3.0")
     * @Route("/api/user", name="api.user.create", defaults={"auth_required"=true}, methods={"POST"})
     * @Acl({"user:create"})
     */
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

        $events = $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data) {
            return $this->userRepository->upsert([$data], $context);
        });

        $event = $events->getEventByEntityName(UserDefinition::ENTITY_NAME);

        $eventIds = $event->getIds();
        $entityId = array_pop($eventIds);

        return $factory->createRedirectResponse($this->userRepository->getDefinition(), $entityId, $request, $context);
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/api/user/{userId}", name="api.user.update", defaults={"auth_required"=true}, methods={"PATCH"})
     * @Acl({"user:update"})
     */
    public function updateUser(?string $userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        return $this->upsertUser($userId, $request, $context, $factory);
    }

    /**
     * @Since("6.3.2.0")
     * @Route("/api/acl-role", name="api.acl_role.create", defaults={"auth_required"=true}, methods={"POST"})
     * @Acl({"acl_role:create"})
     */
    public function upsertRole(?string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        if (!$this->hasScope($request, UserVerifiedScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException(sprintf('This access token does not have the scope "%s" to process this Request', UserVerifiedScope::IDENTIFIER));
        }

        $data = $request->request->all();

        if (!isset($data['id'])) {
            $data['id'] = $roleId ?? null;
        }

        $events = $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data) {
            return $this->roleRepository->upsert([$data], $context);
        });

        $event = $events->getEventByEntityName(AclRoleDefinition::ENTITY_NAME);

        $eventIds = $event->getIds();
        $entityId = array_pop($eventIds);

        return $factory->createRedirectResponse($this->roleRepository->getDefinition(), $entityId, $request, $context);
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/api/acl-role/{roleId}", name="api.acl_role.update", defaults={"auth_required"=true}, methods={"PATCH"})
     * @Acl({"acl_role:update"})
     */
    public function updateRole(?string $roleId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        return $this->upsertRole($roleId, $request, $context, $factory);
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/api/user/{userId}/acl-roles/{roleId}", name="api.user_role.delete", defaults={"auth_required"=true}, methods={"DELETE"})
     * @Acl({"acl_user_role:delete"})
     */
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

    /**
     * @Since("6.3.2.0")
     * @Route("/api/acl-role/{roleId}", name="api.acl_role.delete", defaults={"auth_required"=true}, methods={"DELETE"})
     * @Acl({"acl_role:delete"})
     */
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
