<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\Service\UserValidationService;
use Shopware\Core\System\User\UserException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('fundamentals@framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class UserValidationController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly UserValidationService $userValidationService)
    {
    }

    #[Route(
        path: 'api/_action/user/check-email-unique',
        name: 'api.action.check-email-unique',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['user:read']],
        methods: [Request::METHOD_POST]
    )]
    public function isEmailUnique(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('email')) {
            throw UserException::missingRequestParameter('email');
        }

        if (!$request->request->has('id')) {
            throw UserException::missingRequestParameter('id');
        }

        $email = (string) $request->request->get('email');
        $id = (string) $request->request->get('id');

        return new JsonResponse(
            ['emailIsUnique' => $this->userValidationService->checkEmailUnique($email, $id, $context)]
        );
    }

    #[Route(
        path: 'api/_action/user/check-username-unique',
        name: 'api.action.check-username-unique',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['user:read']],
        methods: [Request::METHOD_POST]
    )]
    public function isUsernameUnique(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('username')) {
            throw UserException::missingRequestParameter('username');
        }

        if (!$request->request->has('id')) {
            throw UserException::missingRequestParameter('id');
        }

        $username = (string) $request->request->get('username');
        $id = (string) $request->request->get('id');

        return new JsonResponse(
            ['usernameIsUnique' => $this->userValidationService->checkUsernameUnique($username, $id, $context)]
        );
    }
}
