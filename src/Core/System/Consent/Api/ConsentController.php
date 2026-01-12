<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Consent\Service\ConsentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
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

    #[Route(path: '/api/consents/{consent}/accept', name: 'api.consents.accept', defaults: ['auth_required' => true], methods: ['POST'])]
    public function acceptConsent(Context $context, string $consent): Response
    {
        $this->consentService->acceptConsent($consent, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/consents/{consent}/revoke', name: 'api.consents.revoke', defaults: ['auth_required' => true], methods: ['POST'])]
    public function revokeConsent(Context $context, string $consent): Response
    {
        $this->consentService->revokeConsent($consent, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
