<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Content\Saas\SaasService;
use Shopware\Core\Content\SaasUser\SaasUserInvitationMailService;
use Shopware\Core\Content\SaasUser\SaasUserService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('after-sales')]
class AdminSaasController extends AbstractController
{
    public function __construct(
        private readonly SaasUserService $saasUserService,
        private readonly SaasUserInvitationMailService $saasUserInvitationMailService,
        private readonly SaasService $saasService,
    ) {
    }

    #[Route(path: '/api/_info/is-saas', name: 'api.info.is-saas', defaults: ['auth_required' => true, '_routeScope' => ['administration']], methods: ['GET'])]
    public function isSaas(): JsonResponse
    {
        return new JsonResponse(['isSaas' => $this->saasService->isSaas()]);
    }

    #[Route(path: '/api/_action/saas/invite-user', name: 'api.action.saas.invite-user', defaults: ['auth_required' => true, '_routeScope' => ['administration']], methods: ['POST'])]
    public function inviteUser(RequestDataBag $requestDataBag, Context $context): JsonResponse
    {
        $email = $requestDataBag->get('email');
        $localeId = $requestDataBag->get('localeId');

        $this->saasUserService->inviteUser($email, $localeId, $context);
        $this->saasUserInvitationMailService->sendInvitationMailToUser($email, $localeId, $context);

        return new JsonResponse();
    }
}
