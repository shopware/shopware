<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Consent\Service\ConsentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Package('data-services')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ConsentController extends AbstractController
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {}

    #[Route(path: '/api/consents', name: 'api.consents.fetch', methods: ['GET'])]
    public function fetchConsents(): Response
    {
        return new JsonResponse($this->consentService->list());
    }

    #[Route(path: '/api/consents/{identifier}/accept', name: 'api.consents.accept', methods: ['POST'])]
    public function acceptConsent(string $identifier, Context $context): Response
    {
        $this->consentService->acceptConsent($identifier, $context);

        return new JsonResponse(['status' => 'accepted']);
    }

    #[Route(path: '/api/consents/{identifier}/revoke', name: 'api.consents.revoke', methods: ['POST'])]
    public function revokeConsent(string $identifier, Context $context): Response
    {
        $this->consentService->revokeConsent($identifier, $context);

        return new JsonResponse(['status' => 'revoked']);
    }
}