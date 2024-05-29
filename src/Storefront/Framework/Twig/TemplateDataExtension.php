<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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
        private readonly bool $showStagingBanner,
        private readonly Connection $connection
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

        $controllerInfo = $this->getControllerInfo($request);

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        $navigation = $this->navigationPath($request, $context);

        return [
            'shopware' => [
                'dateFormat' => \DATE_ATOM,
            ],
            'language' => $this->getLanguage($context),
            'navigationId' => $navigation['id'],
            'navigationPath' => $navigation['path'],
            'minSearchLength' => $this->minSearchLength($context),
            'themeId' => $themeId,
            'controllerName' => (string) $controllerInfo->getName(),
            'controllerAction' => (string) $controllerInfo->getAction(),
            'context' => $context,
            'activeRoute' => $request->attributes->get('_route'),
            'formViolations' => $request->attributes->get('formViolations'),
            'showStagingBanner' => $this->showStagingBanner,
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
        preg_match('/Controller\\\\(\w+)Controller::?(\w+)$/', (string) $controller, $matches);

        if ($matches) {
            $controllerInfo->setName($matches[1]);
            $controllerInfo->setAction($matches[2]);
        }

        return $controllerInfo;
    }

    private function minSearchLength(SalesChannelContext $context): int
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('min_search_length')
            ->from('product_search_config')
            ->where('language_id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($context->getLanguageId()));

        $min = $query->executeQuery()->fetchOne();

        return $min ? (int) $min : 3;
    }

    /**
     * @return array<mixed>
     */
    private function getLanguage(SalesChannelContext $context): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select(['LOWER(HEX(language.id)) as id', 'language.name', 'LOWER(HEX(locale.id)) as localeId', 'locale.code as locale'])
            ->from('language')
            ->innerJoin('language', 'locale', 'locale', 'language.translation_code_id = locale.id')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($context->getContext()->getLanguageId()));

        $data = $query->executeQuery()->fetchAssociative();

        return $data === false ? [] : $data;
    }

    /**
     * @return array<mixed>
     */
    private function navigationPath(Request $request, SalesChannelContext $context): array
    {
        $active = (string) $request->get('navigationId', $context->getSalesChannel()->getNavigationCategoryId());

        $path = $this->connection->fetchOne('SELECT path FROM category WHERE id = :id', ['id' => Uuid::fromHexToBytes($active)]);

        $path = array_filter(explode('|', (string) $path));

        $path = array_flip($path);

        unset($path[$context->getSalesChannel()->getNavigationCategoryId()]);

        return [
            'id' => $active,
            'path' => array_flip($path),
        ];
    }
}
