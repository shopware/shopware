<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Consent\Service\ConsentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:CONSENT_MANAGEMENT
 */
#[Package('data-services')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ConsentController
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {
    }

    #[Route(path: '/api/consents', name: 'api.consents.fetch', defaults: ['auth_required' => true], methods: ['GET'])]
    public function fetchConsents(Context $context): Response
    {
        return new JsonResponse($this->consentService->list($context));
    }

    #[Route(path: '/api/consents/accept', name: 'api.consents.accept', defaults: ['auth_required' => true], methods: ['POST'])]
    public function acceptConsent(Context $context, Request $request): Response
    {
        $consent = $request->request->getString('consent');

        return new JsonResponse($this->consentService->acceptConsent($consent, $context), Response::HTTP_OK);
    }

    #[Route(path: '/api/consents/revoke', name: 'api.consents.revoke', defaults: ['auth_required' => true], methods: ['POST'])]
    public function revokeConsent(Context $context, Request $request): Response
    {
        $consent = $request->request->getString('consent');

        return new JsonResponse($this->consentService->revokeConsent($consent, $context), Response::HTTP_OK);
    }
}
