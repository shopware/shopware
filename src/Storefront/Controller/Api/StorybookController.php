<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller\Api;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Defaults;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('framework')]
class StorybookController extends AbstractController
{
    private array $parameterDenyList;
    private array $entityPropertyList;
    private string $baseTemplate;

    public function __construct(
        private readonly Environment $twig,
        private readonly SalesChannelRepository $productRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly CachedSalesChannelContextFactory $contextFactory,
        private readonly EntityRepository $salesChannelRepository,
        private readonly DatabaseSalesChannelThemeLoader $themeLoader,
        private readonly Connection $connection,
    ) {
        $this->parameterDenyList = ['measureEnabled', 'backgrounds', 'outline', 'viewport'];
        $this->entityPropertyList = ['product', 'category', 'media'];

        $this->baseTemplate = <<<TWIG
            {% set assets = theme_config('assets.css') %}
            {% for file in assets %}
                <link rel="stylesheet"
                    href="{{ asset(file, 'theme') }}">
            {% endfor %}
        TWIG;
    }

    #[Route(
        path: '/store-api/storybook/{component}',
        name: 'store-api.storybook.component',
        defaults: ['auth_required' => false],
        methods: ['GET']
    )]
    public function storybook(Request $request, ?SalesChannelContext $context = null): Response
    {
        $isDev = EnvironmentHelper::getVariable('APP_ENV', 'prod') === 'dev';

        // Only allow in development environment
        if (!$isDev || !Feature::isActive('STOREFRONT_COMPONENTS')) {
            throw new NotFoundHttpException('Route not found');
        }

        // Build SalesChannelContext
        if ($context === null) {
            $salesChannel = $this->getFirstAvailableSalesChannel();
            $salesChannelId = $salesChannel->getId();
            $context = $this->contextFactory->create('', $salesChannelId);
        }

        $this->twig->addGlobal('context', $context);

        $themeId = $this->getThemeIdFromSalesChannel($salesChannelId);
        $this->twig->addGlobal('themeId', $themeId);

        // Set theme ID in request attributes for ThemeAssetPackage
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, $themeId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);

        $component = $request->attributes->get('component');

        try {
            $properties = $this->getPropertiesFromStoryParameters($request, $context);

            // Resolve properties that reference entities.
            $data = $this->resolveEntityProperties($properties, $context);

            $templateString = $this->baseTemplate . '{{ component("' . $component . '", ' . $this->convertPropertiesToTwig($properties) . ') }}';
            $template = $this->twig->createTemplate($templateString);

            $content = $this->twig->render($template, $data);

            $response = new Response($content);

        } catch (RuntimeError|SyntaxError $e) {
            $response = new Response(
                '<div style="color: red; padding: 20px;">Template error: ' . htmlspecialchars($e->getMessage()) . '</div>',
                500
            );
        }

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Content-Type', 'text/html');

        return $response;
    }

    private function getPropertiesFromStoryParameters(Request $request, SalesChannelContext $context): array
    {
        $parameters = [];
        $queryParams = $request->query->all();

        foreach ($queryParams as $key => $value) {
            if (!in_array($key, $this->parameterDenyList)) {
                if (in_array($key, $this->entityPropertyList)) {
                    $parameters[$key] = $key;
                } else {
                    $parameters[$key] = $value;
                }
            }
        }

        return $parameters;
    }

    private function convertPropertiesToTwig(array $properties): string
    {
        $parameters = "";

        foreach ($properties as $key => $value) {
            if (in_array($key, $this->entityPropertyList)) {
                $parameters .= $key . ": " . $key . ", ";
            } else {
                $parameters .= $key . ": '" . $value . "', ";
            }
        }

        return "{ " . $parameters . "}";
    }

    private function resolveEntityProperties(array $properties, SalesChannelContext $context): mixed
    {
        $data = [];

        foreach ($properties as $value) {
            switch ($value) {
                case 'product':
                    $data[$value] = $this->resolveProductProperty($context);
                    break;
                case 'media':
                    $data[$value] = $this->resolveMediaProperty($context);
                    break;
            }
        }

        return $data;
    }

    private function resolveProductProperty(SalesChannelContext $context): ?SalesChannelProductEntity
    {
        $criteria = new Criteria();

        $criteria->addAssociation('media.media');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('options.group');

        $result = $this->productRepository->search($criteria, $context);

        return $result->getEntities()->first();
    }

    private function resolveMediaProperty(SalesChannelContext $context): ?MediaEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        // Filter for JPEG and PNG MIME types only using OR condition
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('mimeType', 'image/jpeg'),
            new EqualsFilter('mimeType', 'image/png')
        ]));

        $result = $this->mediaRepository->search($criteria, $context->getContext());

        return $result->getEntities()->first();
    }

    private function getFirstAvailableSalesChannel(): SalesChannelEntity
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();

        if (!$salesChannel instanceof SalesChannelEntity) {
            throw new \RuntimeException('No sales channel found');
        }

        return $salesChannel;
    }

    private function getThemeIdFromSalesChannel(string $salesChannelId): ?string
    {
        $themes = $this->themeLoader->load($salesChannelId);

        // The loader returns theme names, but we need the theme ID.
        // Get the theme ID from the database using the theme name.
        if (!empty($themes)) {
            $themeName = $themes[0];
            return $this->getThemeIdByName($themeName);
        }

        return null;
    }

    private function getThemeIdByName(string $themeName): ?string
    {
        $themeId = $this->connection->fetchOne('
            SELECT LOWER(HEX(id))
            FROM theme
            WHERE technical_name = :themeName
        ', [
            'themeName' => $themeName,
        ]);

        return $themeId ?: null;
    }
}
