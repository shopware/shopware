<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

#[Package('storefront')]
class TemplateDataExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly bool $showStagingBanner
    ) {
    }

    /**
     * @return array<string, mixed>
     */
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

        [$controllerName, $controllerAction] = $this->getControllerInfo($request);

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        return [
            'shopware' => [
                'dateFormat' => \DATE_ATOM,
            ],
            'themeId' => $themeId,
            'controllerName' => $controllerName,
            'controllerAction' => $controllerAction,
            'context' => $context,
            'activeRoute' => $request->attributes->get('_route'),
            'formViolations' => $request->attributes->get('formViolations'),
            'showStagingBanner' => $this->showStagingBanner,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getControllerInfo(Request $request): array
    {
        $controller = $request->attributes->get('_controller');

        if (!$controller) {
            return ['', ''];
        }

        $matches = [];
        preg_match('/Controller\\\\(\w+)Controller::?(\w+)$/', (string) $controller, $matches);

        if ($matches) {
            return [$matches[1], $matches[2]];
        }

        return ['', ''];
    }
}
