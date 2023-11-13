<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\User\Recovery\UserRecoveryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('system-settings')]
class UserRecoveryController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly UserRecoveryService $userRecoveryService,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    #[Route(path: '/api/_action/user/user-recovery', defaults: ['auth_required' => false], name: 'api.action.user.user-recovery', methods: ['POST'])]
    public function createUserRecovery(Request $request, Context $context): Response
    {
        $email = (string) $request->request->get('email');

        $this->rateLimiter->ensureAccepted(
            RateLimiter::USER_RECOVERY,
            strtolower($email) . '-' . $request->getClientIp()
        );

        $this->userRecoveryService->generateUserRecovery($email, $context);

        return new Response();
    }

    #[Route(path: '/api/_action/user/user-recovery/hash', defaults: ['auth_required' => false], name: 'api.action.user.user-recovery.hash', methods: ['GET'])]
    public function checkUserRecovery(Request $request, Context $context): Response
    {
        $hash = (string) $request->query->get('hash');

        if ($hash !== '' && $this->userRecoveryService->checkHash($hash, $context)) {
            return new Response();
        }

        return $this->getErrorResponse();
    }

    #[Route(path: '/api/_action/user/user-recovery/password', defaults: ['auth_required' => false], name: 'api.action.user.user-recovery.password', methods: ['PATCH'])]
    public function updateUserPassword(Request $request, Context $context): Response
    {
        $hash = (string) $request->request->get('hash');
        $password = (string) $request->request->get('password');
        $passwordConfirm = (string) $request->request->get('passwordConfirm');

        if ($passwordConfirm !== $password) {
            return $this->getErrorResponse();
        }

        $user = $this->userRecoveryService->getUserByHash($hash, $context);
        if ($user === null) {
            return $this->getErrorResponse();
        }

        if (!$this->userRecoveryService->updatePassword($hash, $password, $context)) {
            return $this->getErrorResponse();
        }

        $this->rateLimiter->reset(RateLimiter::OAUTH, strtolower($user->getUsername()) . '-' . $request->getClientIp());
        $this->rateLimiter->reset(RateLimiter::USER_RECOVERY, strtolower($user->getEmail()) . '-' . $request->getClientIp());

        return new Response();
    }

    private function getErrorResponse(): Response
    {
        return new Response('', Response::HTTP_BAD_REQUEST);
    }
}
