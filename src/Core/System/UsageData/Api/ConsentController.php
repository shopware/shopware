<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Api;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Consent\BannerService;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyAcceptedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRequestedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRevokedException;
use Shopware\Core\System\UsageData\UsageDataException;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @param EntityRepository<UserConfigCollection> $userConfigRepository
 */
#[Package('data-services')]
#[Route(defaults: ['_routeScope' => ['api']])]
class ConsentController extends AbstractController
{
    public function __construct(
        private readonly ConsentService $consentService,
        private readonly BannerService $bannerService,
    ) {
    }

    #[Route(path: '/api/usage-data/consent', name: 'api.usage_data.get_consent', methods: [Request::METHOD_GET])]
    public function getConsent(Context $context): JsonResponse
    {
        $userId = $this->getUserIdFromContext($context);

        try {
            $this->consentService->requestConsent();
        } catch (ConsentAlreadyRequestedException) {
        }

        return new JsonResponse([
            'isConsentGiven' => $this->consentService->isConsentAccepted(),
            'isBannerHidden' => $this->bannerService->hasUserHiddenConsentBanner($userId, Context::createDefaultContext()),
        ]);
    }

    #[Route(path: '/api/usage-data/accept-consent', name: 'api.usage_data.accept_consent', methods: [Request::METHOD_POST])]
    public function acceptConsent(Context $context): Response
    {
        $this->getUserIdFromContext($context);

        try {
            $this->consentService->acceptConsent();
        } catch (ConsentAlreadyAcceptedException) {
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/usage-data/revoke-consent', name: 'api.usage_data.revoke_consent', methods: [Request::METHOD_POST])]
    public function revokeConsent(Context $context): Response
    {
        $this->getUserIdFromContext($context);

        try {
            $this->consentService->revokeConsent();
        } catch (ConsentAlreadyRevokedException) {
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/usage-data/hide-consent-banner', name: 'api.usage_data.hide_consent_banner', methods: [Request::METHOD_POST])]
    public function hideConsentBanner(Context $context): Response
    {
        $userId = $this->getUserIdFromContext($context);

        $this->bannerService->hideConsentBannerForUser($userId, $context);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function getUserIdFromContext(Context $context): string
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            throw UsageDataException::invalidContextSource(AdminApiSource::class, $source::class);
        }

        if ($source->getUserId() === null) {
            throw UsageDataException::missingUserInContextSource($source::class);
        }

        return $source->getUserId();
    }
}
