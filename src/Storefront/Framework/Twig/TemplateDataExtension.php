<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class TemplateDataExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var ThemeService
     */
    private $themeService;

    /**
     * @var bool
     */
    private $csrfEnabled;

    /**
     * @var string
     */
    private $csrfMode;

    public function __construct(
        RequestStack $requestStack,
        SystemConfigService $systemConfigService,
        ThemeService $themeService,
        bool $csrfEnabled,
        string $csrfMode
    ) {
        $this->requestStack = $requestStack;
        $this->systemConfigService = $systemConfigService;
        $this->themeService = $themeService;
        $this->csrfEnabled = $csrfEnabled;
        $this->csrfMode = $csrfMode;
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return [];
        }

        /** @var SalesChannelContext|null $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        if (!$context) {
            return [];
        }

        $controllerInfo = $this->getControllerInfo($request);

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        return [
            'shopware' => [
                'config' => $this->getConfig($context),
                'theme' => $this->getThemeConfig($context->getSalesChannel()->getId(), $themeId),
                'dateFormat' => DATE_ATOM,
                'csrfEnabled' => $this->csrfEnabled,
                'csrfMode' => $this->csrfMode,
            ],
            'controllerName' => $controllerInfo->getName(),
            'controllerAction' => $controllerInfo->getAction(),
            'context' => $context,
            'activeRoute' => $request->attributes->get('_route'),
            'formViolations' => $request->attributes->get('formViolations'),
        ];
    }

    protected function getThemeConfig(string $salesChannelId, ?string $themeId): array
    {
        $themeConfig = [
            'breakpoint' => [
                'xs' => 0,
                'sm' => 576,
                'md' => 768,
                'lg' => 992,
                'xl' => 1200,
            ],
        ];

        if (!$themeId) {
            return $themeConfig;
        }

        $themePrefix = ThemeCompiler::getThemePrefix($salesChannelId, $themeId);

        $themeConfig = array_merge(
            $themeConfig,
            [
                'assets' => [
                    'css' => [
                        'theme/' . $themePrefix . '/css/all.css',
                    ],
                    'js' => [
                        'theme/' . $themePrefix . '/js/all.js',
                    ],
                ],
            ],
            $this->themeService->getResolvedThemeConfiguration($themeId, Context::createDefaultContext())
        );

        return $themeConfig;
    }

    private function getDefaultConfiguration(): array
    {
        return [
            'seo' => [
                'descriptionMaxLength' => 150,
            ],
            'cms' => [
                'revocationNoticeCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
                'taxCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
                'tosCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
            ],
            'confirm' => [
                'revocationNotice' => true,
            ],
        ];
    }

    private function getControllerInfo(Request $request): ControllerInfo
    {
        $controllerInfo = new ControllerInfo();
        $controller = $request->attributes->get('_controller');

        if (!$controller) {
            return $controllerInfo;
        }

        $matches = [];
        preg_match('/Controller\\\\(\w+)Controller::?(\w+)$/', $controller, $matches);

        if ($matches) {
            $controllerInfo->setName($matches[1]);
            $controllerInfo->setAction($matches[2]);
        }

        return $controllerInfo;
    }

    private function getConfig(SalesChannelContext $context): array
    {
        $config = array_merge(
            $this->getDefaultConfiguration(),
            $this->systemConfigService->all($context->getSalesChannel()->getId())
        );

        /* @deprecated tag:v6.3.0 - Use core.listing.showReview instead */
        $config['detail']['showReviews'] = $config['core']['listing']['showReview'];

        return $config;
    }
}
