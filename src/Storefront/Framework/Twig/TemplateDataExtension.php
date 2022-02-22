<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Feature;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
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
        bool $csrfEnabled,
        string $csrfMode
    ) {
        $this->requestStack = $requestStack;
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

        /** @deprecated tag:v6.5.0 - Variable and all checks will be removed */
        $isBootstrap5 = Feature::isActive('v6.5.0.0');

        return [
            'shopware' => [
                'dateFormat' => \DATE_ATOM,
                'csrfEnabled' => $this->csrfEnabled,
                'csrfMode' => $this->csrfMode,
            ],
            'themeId' => $themeId,
            'controllerName' => (string) $controllerInfo->getName(),
            'controllerAction' => (string) $controllerInfo->getAction(),
            'context' => $context,
            'activeRoute' => $request->attributes->get('_route'),
            'formViolations' => $request->attributes->get('formViolations'),

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename attribute `data-toggle` to `data-bs-toggle`
             * @see https://getbootstrap.com/docs/5.0/migration/#javascript
             */
            'dataBsToggleAttr' => $isBootstrap5 ? 'data-bs-toggle' : 'data-toggle',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename attribute `data-dismiss` to `data-bs-dismiss`
             * @see https://getbootstrap.com/docs/5.0/components/modal/#modal-components
             */
            'dataBsDismissAttr' => $isBootstrap5 ? 'data-bs-dismiss' : 'data-dismiss',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename attribute `data-target` to `data-bs-target`
             * @see https://getbootstrap.com/docs/5.0/components/dropdowns/#dropdown-options
             */
            'dataBsTargetAttr' => $isBootstrap5 ? 'data-bs-target' : 'data-target',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename attribute `data-offset` to `data-bs-offset`
             * @see https://getbootstrap.com/docs/5.0/components/dropdowns/#dropdown-options
             */
            'dataBsOffsetAttr' => $isBootstrap5 ? 'data-bs-offset' : 'data-offset',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename `custom-select` to `form-select`
             * @see https://getbootstrap.com/docs/5.0/migration/#forms
             */
            'formSelectClass' => $isBootstrap5 ? 'form-select' : 'custom-select',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename class `no-gutters` to `g-0`
             * @see https://getbootstrap.com/docs/5.0/migration/#grid-updates
             */
            'gridNoGuttersClass' => $isBootstrap5 ? 'g-0' : 'no-gutters',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename class `custom-checkbox` to `form-check`
             * @see https://getbootstrap.com/docs/5.0/migration/#forms
             */
            'formCheckboxWrapperClass' => $isBootstrap5 ? 'form-check' : 'custom-control custom-checkbox',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename class `custom-switch` to `form-switch`
             * @see https://getbootstrap.com/docs/5.0/migration/#forms
             */
            'formSwitchWrapperClass' => $isBootstrap5 ? 'form-check form-switch' : 'custom-control custom-switch',

            /**
             * @deprecated tag:v6.5.0 -  Bootstrap v5 will replace classes `custom-control custom-radio` to only `form-check`
             * @see https://getbootstrap.com/docs/5.0/forms/checks-radios/#radios
             */
            'formRadioWrapperClass' => $isBootstrap5 ? 'form-check' : 'custom-control custom-radio',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will drop class `custom-control-input` and replace it with respective `form-*-input` class
             * @see https://getbootstrap.com/docs/5.0/migration/#forms
             */
            'formCheckInputClass' => $isBootstrap5 ? 'form-check-input' : 'custom-control-input',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will drop class `custom-control-label` and replace it with respective `form-*-label` class
             * @see https://getbootstrap.com/docs/5.0/migration/#forms
             */
            'formCheckLabelClass' => $isBootstrap5 ? 'form-check-label' : 'custom-control-label',

            /**
             * @deprecated tag:v6.5.0 -  Bootstrap v5 will drop class `form-row`. Use grid utility `row` with `g-2` instead.
             * @see https://getbootstrap.com/docs/5.0/migration/#grid-updates
             */
            'formRowClass' => $isBootstrap5 ? 'row g-2' : 'form-row',

            /**
             * @deprecated tag:v6.5.0 -  Bootstrap v5 will drop class `modal-close`. Use class `btn-close` instead.
             * @see https://getbootstrap.com/docs/5.0/components/modal/#modal-components
             */
            'modalCloseBtnClass' => $isBootstrap5 ? 'btn-close' : 'modal-close',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename class `sr-only` to `visually-hidden`
             * @see https://getbootstrap.com/docs/5.0/migration/#helpers
             */
            'visuallyHiddenClass' => $isBootstrap5 ? 'visually-hidden' : 'sr-only',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename class `float-left` to `float-start`
             * @see https://getbootstrap.com/docs/5.0/migration/#utilities
             */
            'floatStartClass' => $isBootstrap5 ? 'float-start' : 'float-left',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 will rename class `float-right` to `float-end`
             * @see https://getbootstrap.com/docs/5.0/migration/#utilities
             */
            'floatEndClass' => $isBootstrap5 ? 'float-end' : 'float-right',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 drops all `badge-*` classes in favor of `bg-*` utility classes
             * @see https://getbootstrap.com/docs/5.0/migration/#badges
             */
            'bgClass' => $isBootstrap5 ? 'bg' : 'badge',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 renames all `pl-*` utility classes to `ps-*`
             * @see https://getbootstrap.com/docs/5.0/migration/#utilities
             */
            'paddingStartClass' => $isBootstrap5 ? 'ps' : 'pl',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 renames all `pr-*` utility classes to `pe-*`
             * @see https://getbootstrap.com/docs/5.0/migration/#utilities
             */
            'paddingEndClass' => $isBootstrap5 ? 'pe' : 'pr',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 renames all `ml-*` utility classes to `ms-*`
             * @see https://getbootstrap.com/docs/5.0/migration/#utilities
             */
            'marginStartClass' => $isBootstrap5 ? 'ms' : 'ml',

            /**
             * @deprecated tag:v6.5.0 - Bootstrap v5 renames all `mr-*` utility classes to `me-*`
             * @see https://getbootstrap.com/docs/5.0/migration/#utilities
             */
            'marginEndClass' => $isBootstrap5 ? 'me' : 'mr',
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
