<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
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
     * @var EntityRepositoryInterface
     */
    private $systemConfigRepository;

    public function __construct(RequestStack $requestStack, EntityRepositoryInterface $systemConfigRepository)
    {
        $this->requestStack = $requestStack;
        $this->systemConfigRepository = $systemConfigRepository;
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
                'config' => $this->getSystemConfig($context),
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

    private function getSystemConfig(SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
            new EqualsFilter('salesChannelId', null),
        ]));

        $systemConfigs = $this->systemConfigRepository->search($criteria, $context->getContext())->getEntities();

        return $this->buildSystemConfigArray($systemConfigs);
    }

    /**
     * the keys of the systemconfigs look like core.loginRegistration.showPhoneNumberField
     * this method splits those strings and builds an array structur
     *
     * ```
     * Array
     * (
     *     [core] => Array
     *         (
     *             [loginRegistration] => Array
     *                 (
     *                     [showPhoneNumberField] => 'somevalue'
     *                 )
     *         )
     * )
     * ```
     */
    private function buildSystemConfigArray(EntityCollection $systemConfigs): array
    {
        $configValues = $this->getDefaultConfiguration();

        /** @var SystemConfigEntity $systemConfig */
        foreach ($systemConfigs as $systemConfig) {
            $keys = explode('.', $systemConfig->getConfigurationKey());

            $configValues = $this->getSubArray($configValues, $keys, $systemConfig->getConfigurationValue());
        }

        return $configValues;
    }

    private function getSubArray(array $configValues, array $keys, $value): array
    {
        $key = array_shift($keys);

        if (empty($keys)) {
            $configValues[$key] = $value;
        } else {
            if (!array_key_exists($key, $configValues)) {
                $configValues[$key] = [];
            }

            $configValues[$key] = $this->getSubArray($configValues[$key], $keys, $value);
        }

        return $configValues;
    }
}
