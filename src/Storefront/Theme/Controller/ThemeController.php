<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Theme\AbstractScssCompiler;
use Shopware\Storefront\Theme\Exception\ThemeConfigException;
use Shopware\Storefront\Theme\ThemeService;
use Shopware\Storefront\Theme\Validator\SCSSValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class ThemeController extends AbstractController
{
    /**
     * @internal
     *
     * @param array<int, string> $customAllowedRegex
     */
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly AbstractScssCompiler $scssCompiler,
        private readonly array $customAllowedRegex = []
    ) {
    }

    /**
     * Retrieves the configuration for a specific theme.
     *
     * This endpoint returns the theme configuration including all configurable fields,
     * their current values, and metadata.
     *
     * @param string $themeId The unique identifier of the theme
     * @param Context $context The application context containing user and system information
     *
     * @return JsonResponse Returns a JSON response containing the theme configuration
     */
    #[Route(path: '/api/_action/theme/{themeId}/configuration', name: 'api.action.theme.configuration', methods: ['GET'])]
    public function configuration(string $themeId, Context $context): JsonResponse
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $themeConfiguration = Feature::silent('v6.8.0.0', function () use ($themeId, $context) {
                return $this->themeService->getThemeConfiguration($themeId, true, $context);
            });

            return new JsonResponse($themeConfiguration);
        }

        $themeConfiguration = $this->themeService->getPlainThemeConfiguration($themeId, $context);

        return new JsonResponse($themeConfiguration);
    }

    /**
     * Updates the configuration of a specific theme.
     *
     * This endpoint allows updating theme configuration values.
     * If the reset parameter is provided, the theme will be reset
     * to its default configuration before applying any new values.
     * If the validate parameter is provided, the theme config will be validated before updating the theme.
     * If the sanitize parameter is provided, the theme config will be sanitized during validation.
     *
     * @param string $themeId The unique identifier of the theme to update
     * @param Request $request The HTTP request containing configuration data
     * @param Context $context The application context containing user and system information
     *
     * @return JsonResponse Returns an empty JSON response on successful update
     */
    #[Route(path: '/api/_action/theme/{themeId}', name: 'api.action.theme.update', methods: ['PATCH'])]
    public function updateTheme(string $themeId, Request $request, Context $context): JsonResponse
    {
        $config = $request->request->all('config');

        $validateConfig = $request->query->getBoolean('validate', false);

        // Validate the theme config before updating the theme.
        if ($validateConfig) {
            $config = $this->themeService->validateThemeConfig(
                $themeId,
                $config,
                $context,
                $this->customAllowedRegex,
                $request->query->getBoolean('sanitize', false)
            );
        }

        if ($request->query->getBoolean('reset')) {
            $this->themeService->resetTheme($themeId, $context);
        }

        $this->themeService->updateTheme(
            $themeId,
            $config,
            (string) $request->request->get('parentThemeId'),
            $context
        );

        return new JsonResponse([]);
    }

    /**
     * Assigns a theme to a specific sales channel.
     *
     * This endpoint links a theme to a sales channel, making it the active theme
     * for that channel. This determines the visual appearance and styling of the
     * storefront for customers visiting that sales channel. Only one theme can be
     * active per sales channel at a time.
     *
     * @param string $themeId The unique identifier of the theme to assign
     * @param string $salesChannelId The unique identifier of the sales channel
     * @param Context $context The application context containing user and system information
     *
     * @return JsonResponse Returns an empty JSON response on successful assignment
     */
    #[Route(path: '/api/_action/theme/{themeId}/assign/{salesChannelId}', name: 'api.action.theme.assign', methods: ['POST'])]
    public function assignTheme(string $themeId, string $salesChannelId, Context $context): JsonResponse
    {
        $this->themeService->assignTheme($themeId, $salesChannelId, $context);

        return new JsonResponse([]);
    }

    /**
     * Resets a theme to its default configuration.
     *
     * This endpoint reverts all custom theme configuration values back to their
     * default settings as defined in the theme's configuration files. This is useful
     * when you want to start fresh with theme customization or when troubleshooting
     * theme-related issues.
     *
     * @param string $themeId The unique identifier of the theme to reset
     * @param Context $context The application context containing user and system information
     *
     * @return JsonResponse Returns an empty JSON response on successful reset
     */
    #[Route(path: '/api/_action/theme/{themeId}/reset', name: 'api.action.theme.reset', methods: ['PATCH'])]
    public function resetTheme(string $themeId, Context $context): JsonResponse
    {
        $this->themeService->resetTheme($themeId, $context);

        return new JsonResponse([]);
    }

    /**
     * Retrieves the structured field configuration for a theme.
     *
     * This endpoint returns the theme configuration organized in a structured format
     * that includes field grouping, dependencies, and metadata. This is particularly
     * useful for building theme configuration interfaces in the administration panel.
     * The response format differs based on the Shopware version for backward compatibility.
     *
     * @param string $themeId The unique identifier of the theme
     * @param Context $context The application context containing user and system information
     *
     * @return JsonResponse Returns a JSON response containing the structured field configuration
     */
    #[Route(path: '/api/_action/theme/{themeId}/structured-fields', name: 'api.action.theme.structuredFields', methods: ['GET'])]
    public function structuredFields(string $themeId, Context $context): JsonResponse
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $themeConfiguration = Feature::silent('v6.8.0.0', function () use ($themeId, $context) {
                return $this->themeService->getThemeConfigurationStructuredFields($themeId, true, $context);
            });

            return new JsonResponse($themeConfiguration);
        }

        $themeConfiguration = $this->themeService->getThemeConfigurationFieldStructure($themeId, $context);

        return new JsonResponse($themeConfiguration);
    }

    /**
     * Validates theme configuration field values.
     *
     * This endpoint validates theme configuration values against their defined types
     * and constraints. It performs SCSS validation for color values, typography settings,
     * and other theme-specific fields. This is typically used in the administration
     * panel to provide real-time validation feedback when users are configuring themes.
     *
     * @param Request $request The HTTP request containing the fields to validate
     *
     * @return JsonResponse Returns an empty JSON response if validation passes, or validation errors
     */
    #[Route(path: '/api/_action/theme/validate-fields', name: 'api.action.theme.validate', methods: ['POST'])]
    public function validateVariables(Request $request): JsonResponse
    {
        $fields = $request->request->all('fields');

        $themeConfigException = new ThemeConfigException();

        foreach ($fields as $key => $data) {
            // if no type is set just use the value and continue
            if (!isset($data['type'])) {
                continue;
            }

            try {
                $data['value'] = SCSSValidator::validate($this->scssCompiler, $data, $this->customAllowedRegex);
            } catch (\Throwable $exception) {
                $themeConfigException->add($exception);
            }
        }

        $themeConfigException->tryToThrow();

        return new JsonResponse([]);
    }
}
