<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\UserRecovery\UserRecoveryService;
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
     * @Route("/admin/create-user-recovery", name="recovery.create", methods={"POST"})
     */
    public function createUserRecovery(Request $request, Context $context): Response
    {
        $email = $request->request->get('email');
        $this->userRecoveryService->generateUserRecovery($email, $context);

        return new Response();
    }

    /**
     * @Route("/admin/check-user-recovery", name="recovery.check", methods={"POST"})
     */
    public function checkUserRecovery(Request $request, Context $context): Response
    {
        $hash = $request->request->get('hash');

        if ($this->userRecoveryService->checkHash($hash, $context)) {
            return new Response();
        }

        return $this->getErrorResponse();
    }

    /**
     * @Route("/admin/user-recovery", name="recovery.recover", methods={"POST"})
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
