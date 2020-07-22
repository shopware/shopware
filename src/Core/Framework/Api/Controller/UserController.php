<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
     * @var UserDefinition
     */
    private $userDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $keyRepository;

    public function __construct(
        EntityRepositoryInterface $userRepository,
        EntityRepositoryInterface $keyRepository,
        UserDefinition $userDefinition
    ) {
        $this->userRepository = $userRepository;
        $this->userDefinition = $userDefinition;
        $this->keyRepository = $keyRepository;
    }

    /**
     * @Route("/api/v{version}/_info/me", name="api.info.me", methods={"GET"})
     */
    public function me(Context $context, Request $request, ResponseFactoryInterface $responseFactory): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
        }

        $userId = $context->getSource()->getUserId();

        $criteria = new Criteria([$userId]);
        $criteria->addAssociation('aclRoles');

        $user = $this->userRepository->search($criteria, $context)->first();
        if (!$user) {
            throw OAuthServerException::invalidCredentials();
        }

        return $responseFactory->createDetailResponse(new Criteria(), $user, $this->userDefinition, $request, $context);
    }

    /**
     * @Route("/api/v{version}/_info/ping", name="api.info.ping", methods={"GET"})
     */
    public function status(Context $context): Response
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
        }

        $userId = $context->getSource()->getUserId();
        $result = $this->userRepository->searchIds(new Criteria([$userId]), $context);

        if ($result->getTotal() === 0) {
            throw OAuthServerException::invalidCredentials();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/user/{userId}", name="api.user.delete", defaults={"auth_required"=true}, methods={"DELETE"})
     */
    public function deleteUser(string $userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        if (!$this->hasScope($request, UserVerifiedScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException(sprintf('This access token does not have the scope "%s" to process this Request', UserVerifiedScope::IDENTIFIER));
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId): void {
            $this->userRepository->delete([['id' => $userId]], $context);
        });

        return $factory->createRedirectResponse($this->userRepository->getDefinition(), $userId, $request, $context);
    }

    /**
     * @Route("/api/v{version}/user/{userId}/access-keys/{id}", name="api.user_access_keys.delete", defaults={"auth_required"=true}, methods={"DELETE"})
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
     * @Route("/api/v{version}/user", name="api.user.create", defaults={"auth_required"=true}, methods={"POST"})
     * @Route("/api/v{version}/user/{userId}", name="api.user.update", defaults={"auth_required"=true}, methods={"PATCH"})
     */
    public function upsertUser(?string $userId, Request $request, Context $context, ResponseFactoryInterface $factory): Response
    {
        if (!$this->hasScope($request, UserVerifiedScope::IDENTIFIER)) {
            throw new AccessDeniedHttpException(sprintf('This access token does not have the scope "%s" to process this Request', UserVerifiedScope::IDENTIFIER));
        }

        $data = $request->request->all();

        if (!isset($data['id'])) {
            $data['id'] = $userId ?? null;
        }

        $events = $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data) {
            return $this->userRepository->upsert([$data], $context);
        });

        $event = $events->getEventByEntityName(UserDefinition::ENTITY_NAME);

        $eventIds = $event->getIds();
        $entityId = array_pop($eventIds);

        return $factory->createRedirectResponse($this->userRepository->getDefinition(), $entityId, $request, $context);
    }

    private function hasScope(Request $request, string $scopeIdentifier): bool
    {
        $scopes = array_flip($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES));

        return isset($scopes[$scopeIdentifier]);
    }
}
