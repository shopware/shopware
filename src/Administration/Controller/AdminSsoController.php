<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Content\SsoUser\SsoUserInvitationMailService;
use Shopware\Core\Content\SsoUser\SsoUserService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoService;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
class AdminSsoController extends AbstractController
{
    public function __construct(
        private readonly SsoUserService $saasUserService,
        private readonly SsoUserInvitationMailService $saasUserInvitationMailService,
        private readonly SsoService $saasService,
    ) {
    }

    #[Route(path: '/api/_info/is-sso', name: 'api.info.is-sso', defaults: ['auth_required' => true, '_routeScope' => ['administration']], methods: ['GET'])]
    public function isSso(): JsonResponse
    {
        return new JsonResponse(['isSso' => $this->saasService->isSso()]);
    }

    #[Route(path: '/api/_action/sso/invite-user', name: 'api.action.sso.invite-user', defaults: ['auth_required' => true, '_routeScope' => ['administration']], methods: ['POST'])]
    public function inviteUser(RequestDataBag $requestDataBag, Context $context): JsonResponse
    {
        $email = $requestDataBag->get('email');
        $localeId = $requestDataBag->get('localeId');

        $this->saasUserService->inviteUser($email, $localeId, $context);
        $this->saasUserInvitationMailService->sendInvitationMailToUser($email, $localeId, $context);

        return new JsonResponse();
    }
}
