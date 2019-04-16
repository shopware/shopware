<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
     * @Route("/api/v{version}/_action/user/user-recovery", name="api.action.user.user-recovery", methods={"POST"})
     */
    public function createUserRecovery(Request $request, Context $context): Response
    {
        $email = $request->request->get('email');
        $this->userRecoveryService->generateUserRecovery($email, $context);

        return new Response();
    }

    /**
     * @Route("/api/v{version}/_action/user/user-recovery/hash", name="api.action.user.user-recovery.hash", methods={"GET"})
     */
    public function checkUserRecovery(Request $request, Context $context): Response
    {
        $hash = $request->query->get('hash');

        if ($this->userRecoveryService->checkHash($hash, $context)) {
            return new Response();
        }

        return $this->getErrorResponse();
    }

    /**
     * @Route("/api/v{version}/_action/user/user-recovery/password", name="api.action.user.user-recovery.password", methods={"PATCH"})
     */
    public function updateUserPassword(Request $request, Context $context): Response
    {
        $hash = $request->request->get('hash');
        $password = $request->request->get('password');
        $passwordConfirm = $request->request->get('passwordConfirm');

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
