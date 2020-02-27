<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ThemeController extends AbstractController
{
    /**
     * @var ThemeService
     */
    private $themeService;

    public function __construct(ThemeService $themeService)
    {
        $this->themeService = $themeService;
    }

    /**
     * @Route("/api/v{version}/_action/theme/{themeId}/configuration", name="api.action.theme.configuration", methods={"GET"})
     */
    public function configuration(string $themeId, Context $context): JsonResponse
    {
        $themeConfiguration = $this->themeService->getThemeConfiguration($themeId, true, $context);

        return new JsonResponse($themeConfiguration);
    }

    /**
     * @Route("/api/v{version}/_action/theme/{themeId}", name="api.action.theme.update", methods={"PATCH"})
     */
    public function updateTheme(string $themeId, Request $request, Context $context): JsonResponse
    {
        $this->themeService->updateTheme(
            $themeId,
            $request->request->get('config'),
            $request->request->get('parentThemeId'),
            $context
        );

        return new JsonResponse([]);
    }

    /**
     * @Route("/api/v{version}/_action/theme/{themeId}/assign/{salesChannelId}", name="api.action.theme.assign", methods={"POST"})
     */
    public function assignTheme(string $themeId, string $salesChannelId, Context $context): JsonResponse
    {
        $this->themeService->assignTheme($themeId, $salesChannelId, $context);

        return new JsonResponse([]);
    }

    /**
     * @Route("/api/v{version}/_action/theme/{themeId}/reset", name="api.action.theme.reset", methods={"PATCH"})
     */
    public function resetTheme(string $themeId, Context $context): JsonResponse
    {
        $this->themeService->resetTheme($themeId, $context);

        return new JsonResponse([]);
    }

    /**
     * @deprecated tag:v6.4.0 - use structuredFields instead
     * @Route("/api/v{version}/_action/theme/{themeId}/fields", name="api.action.theme.fields", methods={"GET"})
     */
    public function fields(string $themeId, Context $context): JsonResponse
    {
        $themeConfiguration = $this->themeService->getThemeConfigurationFields($themeId, true, $context);

        return new JsonResponse($themeConfiguration);
    }

    /**
     * @Route("/api/v{version}/_action/theme/{themeId}/structured-fields", name="api.action.theme.structuredFields", methods={"GET"})
     */
    public function structuredFields(string $themeId, Context $context): JsonResponse
    {
        $themeConfiguration = $this->themeService->getThemeConfigurationStructuredFields($themeId, true, $context);

        return new JsonResponse($themeConfiguration);
    }
}
