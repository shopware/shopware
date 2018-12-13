<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class TemplateDataExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return [];
        }

        /** @var CheckoutContext|null $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        if (!$context) {
            return [];
        }

        $controllerInfo = $this->getControllerInfo($request);

        return [
            'shopware' => [
                'config' => array_merge(
                    $this->getDefaultConfiguration(),
                    []
                ),
                'theme' => $this->getThemeConfig(),
            ],
            'controllerName' => $controllerInfo->getName(),
            'controllerAction' => $controllerInfo->getAction(),
            'context' => $context,
            'activeRoute' => $request->attributes->get('_route'),
        ];
    }

    /**
     * @return array
     */
    protected function getThemeConfig(): array
    {
        $themeConfig = [];

        $themeConfig = array_merge(
            $themeConfig,
            [
                'desktopLogo' => 'bundles/storefront/src/img/logos/logo--tablet.png',
                'tabletLandscapeLogo' => 'bundles/storefront/src/img/logos/logo--tablet.png',
                'tabletLogo' => 'bundles/storefront/src/img/logos/logo--tablet.png',
                'mobileLogo' => 'bundles/storefront/src/img/logos/logo--mobile.png',
                'ajaxVariantSwitch' => true,
                'offcanvasCart' => true,
            ]
        );

        return $themeConfig;
    }

    private function getDefaultConfiguration(): array
    {
        return [
            'showBirthdayField' => true,
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
