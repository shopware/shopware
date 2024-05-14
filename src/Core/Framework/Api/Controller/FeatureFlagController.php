<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class FeatureFlagController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FeatureFlagRegistry $featureFlagService,
        private readonly CacheClearer $cacheClearer
    ) {
    }

    #[Route(path: '/api/_action/feature-flag/enable/{feature}', name: 'api.action.feature-flag.enable', defaults: ['auth_required' => true, '_acl' => ['api_feature_flag_toggle']], methods: ['POST'])]
    public function enable(string $feature): JsonResponse
    {
        $this->featureFlagService->enable($feature);

        $this->cacheClearer->clear();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/feature-flag/disable/{feature}', name: 'api.action.feature-flag.disable', defaults: ['auth_required' => true, '_acl' => ['api_feature_flag_toggle']], methods: ['POST'])]
    public function disable(string $feature): JsonResponse
    {
        $this->featureFlagService->disable($feature);

        $this->cacheClearer->clear();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/feature-flag', name: 'api.action.feature-flag.load', defaults: ['auth_required' => true, '_acl' => ['api_feature_flag_toggle']], methods: ['GET'])]
    public function load(): JsonResponse
    {
        $featureFlags = Feature::getRegisteredFeatures();

        foreach ($featureFlags as $featureKey => $feature) {
            $featureFlags[$featureKey]['active'] = Feature::isActive($featureKey);
        }

        return new JsonResponse($featureFlags);
    }
}
