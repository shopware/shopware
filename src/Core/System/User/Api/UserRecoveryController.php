<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class UserRecoveryController extends AbstractController
{
    /**
     * @var UserRecoveryService
     */
    private $userRecoveryService;

    public function __construct(UserRecoveryService $userRecoveryService)
    {
        $this->userRecoveryService = $userRecoveryService;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/user/user-recovery", defaults={"auth_required"=false}, name="api.action.user.user-recovery", methods={"POST"})
     */
    public function createUserRecovery(Request $request, Context $context): Response
    {
        $email = (string) $request->request->get('email');
        $this->userRecoveryService->generateUserRecovery($email, $context);

        return new Response();
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/user/user-recovery/hash", defaults={"auth_required"=false}, name="api.action.user.user-recovery.hash", methods={"GET"})
     */
    public function checkUserRecovery(Request $request, Context $context): Response
    {
        $hash = (string) $request->query->get('hash');

        if ($hash !== '' && $this->userRecoveryService->checkHash($hash, $context)) {
            return new Response();
        }

        return $this->getErrorResponse();
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/user/user-recovery/password", defaults={"auth_required"=false}, name="api.action.user.user-recovery.password", methods={"PATCH"})
     */
    public function updateUserPassword(Request $request, Context $context): Response
    {
        $hash = (string) $request->request->get('hash');
        $password = (string) $request->request->get('password');
        $passwordConfirm = (string) $request->request->get('passwordConfirm');

        if ($passwordConfirm !== $password) {
            return $this->getErrorResponse();
        }

        if ($this->userRecoveryService->updatePassword($hash, $password, $context)) {
            return new Response();
        }

        return $this->getErrorResponse();
    }

    private function getErrorResponse(): Response
    {
        return new Response('', Response::HTTP_BAD_REQUEST);
    }
}
