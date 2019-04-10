<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

        /** @var SalesChannelContext|null $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        if (!$context) {
            return [];
        }

        $controllerInfo = $this->getControllerInfo($request);

        return [
            'shopware' => [
                'config' => $this->getDefaultConfiguration(),
                'theme' => $this->getThemeConfig(),
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
                    'favicon' => 'favicon.ico',
                    'appleTouch' => 'apple-touch-icon.png',
                    'androidTouch' => 'android-touch-icon.png',
                    'desktop' => 'img/logo-desktop.png',
                    'tablet' => 'img/logo-desktop.png',
                    'mobile' => 'img/logo-mobile.png',
                ],
                'breakpoint' => [
                    'xs' => '0',
                    'sm' => '576',
                    'md' => '768',
                    'lg' => '992',
                    'xl' => '1200',
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
            'register' => [
                'titleField' => true,
                'emailConfirmation' => false,
                'passwordConfirmation' => false,
                'minPasswordLength' => 8,
                'birthdayField' => true,
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
