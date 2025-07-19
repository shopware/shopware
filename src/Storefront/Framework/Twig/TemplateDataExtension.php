<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

#[Package('framework')]
class TemplateDataExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly bool $showStagingBanner,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return [];
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext) {
            return [];
        }

        [$controllerName, $controllerAction] = $this->getControllerInfo($request);

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        $activeNavigationId = (string) $request->get('navigationId', $context->getSalesChannel()->getNavigationCategoryId());
        $navigationPathIdList = $this->getNavigationPath($activeNavigationId, $context);
        $navigationInfo = new NavigationInfo(
            $activeNavigationId,
            $navigationPathIdList,
        );

        return [
            'shopware' => [
                'dateFormat' => \DATE_ATOM,
                'navigation' => $navigationInfo,
                'minSearchLength' => $this->minSearchLength($context),
                'showStagingBanner' => $this->showStagingBanner,
            ],
            'themeId' => $themeId, /** Not used in Twig template directly, but in @see \Shopware\Storefront\Framework\Twig\Extension\ConfigExtension::getThemeId */
            'controllerName' => $controllerName,
            'controllerAction' => $controllerAction,
            'context' => $context,
            'activeRoute' => $request->attributes->get('_route'),
            'formViolations' => $request->attributes->get('formViolations'),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getControllerInfo(Request $request): array
    {
        $controller = $request->attributes->getString('_controller');
        if ($controller === '') {
            return ['', ''];
        }

        $matches = [];
        preg_match('/Controller\\\\(\w+)Controller::?(\w+)$/', $controller, $matches);
        if ($matches) {
            return [$matches[1], $matches[2]];
        }

        return ['', ''];
    }

    private function minSearchLength(SalesChannelContext $context): int
    {
        $min = (int) $this->connection->fetchOne(
            'SELECT `min_search_length` FROM `product_search_config` WHERE `language_id` = :id',
            ['id' => Uuid::fromHexToBytes($context->getLanguageId())]
        );

        return $min ?: AbstractTokenFilter::DEFAULT_MIN_SEARCH_TERM_LENGTH;
    }

    /**
     * @return list<string>
     */
    private function getNavigationPath(string $activeNavigationId, SalesChannelContext $context): array
    {
        $path = $this->connection->fetchOne(
            'SELECT path FROM category WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($activeNavigationId)]
        ) ?: '';

        $navigationPathIdList = array_filter(explode('|', $path));
        $navigationPathIdList = array_diff($navigationPathIdList, [$context->getSalesChannel()->getNavigationCategoryId()]);

        return array_values($navigationPathIdList);
    }
}
