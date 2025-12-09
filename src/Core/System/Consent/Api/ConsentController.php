<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Api;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Consent\Service\ConsentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Package('data-services')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ConsentController extends AbstractController
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {
    }

    #[Route(path: '/api/consents', name: 'api.consents.fetch', defaults: ['auth_required' => true], methods: ['GET'])]
    public function fetchConsents(Context $context): Response
    {
        $currentUserId = $this->getUserId($context);

        return new JsonResponse($this->consentService->list($currentUserId));
    }

    #[Route(path: '/api/consents/{consent}/accept', name: 'api.consents.accept', defaults: ['auth_required' => true], methods: ['POST'])]
    public function acceptConsent(Context $context, string $consent): Response
    {
        $currentUserId = $this->getUserId($context);

        $this->consentService->acceptConsent($consent, $currentUserId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/consents/{consent}/revoke', name: 'api.consents.revoke', defaults: ['auth_required' => true], methods: ['POST'])]
    public function revokeConsent(Context $context, string $consent): Response
    {
        $currentUserId = $this->getUserId($context);

        $this->consentService->revokeConsent($consent, $currentUserId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function getUserId(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($source::class); /** @phpstan-ignore shopware.domainException */
        }

        $userId = $source->getUserId();
        if (!$userId) {
            throw ApiException::userNotLoggedIn(); /** @phpstan-ignore shopware.domainException */
        }

        return $userId;
    }
}
