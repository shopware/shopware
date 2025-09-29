<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\Exceptions\SsoUserNotFoundException;
use Shopware\Core\Framework\Sso\LoginResponseService;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\SsoService;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserInvitationMailService;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserService;
use Shopware\Core\Framework\Sso\StateValidator;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class SsoController extends AbstractController
{
    public function __construct(
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly LoginConfigService $loginConfigService,
        private readonly LoginResponseService $loginResponseService,
        private readonly StateValidator $stateValidator,
        private readonly SsoUserService $ssoUserService,
        private readonly SsoUserInvitationMailService $ssoUserInvitationMailService,
        private readonly SsoService $ssoService,
    ) {
    }

    #[Route(path: '/api/oauth/sso/config', name: 'api.oauth.sso.config', defaults: ['auth_required' => false], methods: ['GET'])]
    public function loadSsoLoginConfig(Request $request): JsonResponse
    {
        $random = $this->stateValidator->createRandom($request);
        $templateData = $this->loginConfigService->createTemplateData($random);

        return new JsonResponse($templateData);
    }

    #[Route(path: '/api/oauth/sso/code', name: 'api.oauth.sso.code', defaults: ['auth_required' => false], methods: ['GET'])]
    public function callbackWithCode(Request $request): Response
    {
        $this->stateValidator->validateRequest($request);

        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse(new Response());

        try {
            $response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);
            // @phpstan-ignore catch.neverThrown (SsoUserNotFoundException is thrown inside an external library where we implemented an interface)
        } catch (SsoUserNotFoundException $ssoException) {
            return $this->loginResponseService->createErrorResponse($ssoException->getEmail());
        }

        return $this->loginResponseService->create($response);
    }

    #[Route(path: '/api/oauth/sso/auth', name: 'oauth.sso.auth', defaults: ['auth_required' => false], methods: ['GET'])]
    public function ssoAuth(Request $request): RedirectResponse
    {
        $random = $request->getSession()->get(StateValidator::SESSION_KEY);
        if ($random === null) {
            $referer = $request->headers->get('referer');
            if ($referer === null) {
                throw SsoException::refererNotFound();
            }

            return $this->redirect($referer);
        }

        $url = $this->loginConfigService->createRedirectUrl($random);

        return $this->redirect($url);
    }

    #[Route(path: '/api/_info/is-sso', name: 'api.info.is-sso', defaults: ['auth_required' => true, '_routeScope' => ['administration']], methods: ['GET'])]
    public function isSso(): JsonResponse
    {
        return new JsonResponse(['isSso' => $this->ssoService->isSso()]);
    }

    #[Route(path: '/api/_action/sso/invite-user', name: 'api.action.sso.invite-user', defaults: ['auth_required' => true, '_routeScope' => ['administration']], methods: ['POST'])]
    public function inviteUser(RequestDataBag $requestDataBag, Context $context): JsonResponse
    {
        $email = $requestDataBag->get('email');
        $localeId = $requestDataBag->get('localeId');

        $this->ssoUserService->inviteUser($email, $localeId, $context);
        $this->ssoUserInvitationMailService->sendInvitationMailToUser($email, $localeId, $context);

        return new JsonResponse();
    }
}
