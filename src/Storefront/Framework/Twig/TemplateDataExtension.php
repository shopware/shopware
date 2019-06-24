<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use function array_merge;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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

    public function __construct(RequestStack $requestStack, SystemConfigService $systemConfigService)
    {
        $this->requestStack = $requestStack;
        $this->systemConfigService = $systemConfigService;
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

        return [
            'shopware' => [
                'config' => array_merge(
                    $this->systemConfigService->getConfigArray($context->getSalesChannel()->getId()),
                    $this->getDefaultConfiguration()
                ),
                'theme' => $this->getThemeConfig(),
                'dateFormat' => DATE_ATOM,
            ],
            'controllerName' => $controllerInfo->getName(),
            'controllerAction' => $controllerInfo->getAction(),
            'context' => $context,
            'activeRoute' => $request->attributes->get('_route'),
            'formViolations' => $request->attributes->get('formViolations'),
        ];
    }

    protected function getThemeConfig(): array
    {
        $themeConfig = [];

        $themeConfig = array_merge(
            $themeConfig,
            [
                'color' => [
                    'primary' => '#007bff',
                ],
                'logo' => [
                    'favicon' => 'assets/logo/favicon.png',
                    'appleTouch' => 'assets/logo/apple-touch-icon.png',
                    'androidTouch' => 'assets/logo/android-touch-icon.png',
                    'desktop' => 'assets/logo/demostore-logo.png',
                    'tablet' => 'assets/logo/demostore-logo.png',
                    'mobile' => 'assets/logo/demostore-logo.png',
                ],
                'breakpoint' => [
                    'xs' => 0,
                    'sm' => 576,
                    'md' => 768,
                    'lg' => 992,
                    'xl' => 1200,
                ],
            ]
        );

        return $themeConfig;
    }

    private function getDefaultConfiguration(): array
    {
        return [
            'shopName' => 'Shopware Storefront',
            'seo' => [
                'descriptionMaxLength' => 150,
            ],
            'metaIsFamilyFriendly' => true,
            'cms' => [
                'revocationNoticeCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
                'taxCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
                'tosCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
            ],
            'register' => [
                'titleField' => true,
                'emailConfirmation' => false,
                'passwordConfirmation' => false,
            ],
            'address' => [
                'additionalField1' => false,
                'additionalField2' => false,
                'zipBeforeCity' => true,
            ],
            'confirm' => [
                'revocationNotice' => true,
            ],
            'checkout' => [
                'instockinfo' => false,
                'maxQuantity' => 100,
            ],
            'listing' => [
                'allowBuyInListing' => true,
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
}
