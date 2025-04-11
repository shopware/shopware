<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @internal
 */
#[Package('framework')]
class StorybookController extends AbstractController
{
    private const PARAMETER_DENY_LIST = [
        'measureEnabled',
        'backgrounds',
        'outline',
        'viewport',
    ];

    private const ENTITY_PROPERTY_LIST = [
        'product',
        'category',
        'media',
    ];

    private const BASE_TEMPLATE = <<<TWIG
        {% set assets = theme_config('assets.css') %}
        {% for file in assets %}
            <link rel="stylesheet"
                href="{{ asset(file, 'theme') }}">
        {% endfor %}
    TWIG;

    /**
     * @internal
     *
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly string $environment,
        private readonly Environment $twig,
        private readonly SalesChannelRepository $productRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly AbstractSalesChannelContextFactory $contextFactory,
        private readonly EntityRepository $salesChannelRepository,
        private readonly DatabaseSalesChannelThemeLoader $themeLoader,
        private readonly ThemeRuntimeConfigStorage $themeRuntimeConfigStorage,
        private readonly TwigComponentHelper $twigComponentHelper,
    ) {
    }

    /**
     * @phpstan-ignore shopware.routeScope
     */
    #[Route(
        path: '/storybook/{component}',
        name: 'storybook.component',
        defaults: ['auth_required' => false],
        methods: ['GET']
    )]
    public function storybook(string $component, Request $request): Response
    {
        // Only allow in development environment
        if ($this->environment !== 'dev') {
            throw new NotFoundHttpException('Route not found');
        }

        // Only allow requests from Storybook
        if ($request->headers->get('Origin') !== 'http://localhost:6006') {
            throw new NotFoundHttpException('Route not found');
        }

        // Validate component name against the registered components
        $registeredComponents = $this->twigComponentHelper->getComponents();
        if ($registeredComponents->get($component) === null) {
            throw new NotFoundHttpException('Component not found');
        }

        // Build SalesChannelContext
        $salesChannel = $this->getFirstAvailableSalesChannel();
        $salesChannelId = $salesChannel->getId();
        $context = $this->contextFactory->create('', $salesChannelId);

        $this->twig->addGlobal('context', $context);

        $themeId = $this->getThemeIdFromSalesChannel($salesChannelId);
        $this->twig->addGlobal('themeId', $themeId);

        // Set theme ID in request attributes for ThemeAssetPackage
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_ID, $themeId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);

        try {
            $properties = $this->getPropertiesFromStoryParameters($request, $context);

            // Resolve properties that reference entities.
            $componentProps = $this->resolveEntityProperties($properties, $context);

            $templateString = self::BASE_TEMPLATE . '{{ component(componentName, componentProps) }}';
            $template = $this->twig->createTemplate($templateString);

            $content = $this->twig->render($template, [
                'componentName' => $component,
                'componentProps' => $componentProps,
            ]);

            $response = new Response($content);
        } catch (RuntimeError|SyntaxError $e) {
            $response = new Response(
                '<div style="color: red; padding: 20px;">Template error: ' . htmlspecialchars($e->getMessage()) . '</div>',
                500
            );
        }

        $response->headers->set('Access-Control-Allow-Origin', 'http://localhost:6006');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPropertiesFromStoryParameters(Request $request, SalesChannelContext $context): array
    {
        $parameters = [];
        $queryParams = $request->query->all();

        foreach ($queryParams as $key => $value) {
            // Reject keys that are not valid PHP/Twig identifiers
            if (!\is_string($key) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                continue;
            }

            if (\in_array($key, self::PARAMETER_DENY_LIST, true)) {
                continue;
            }

            if (\in_array($key, self::ENTITY_PROPERTY_LIST, true)) {
                // Store the key as a sentinel so resolveEntityProperties knows to fetch this entity.
                $parameters[$key] = $key;
            } else {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Returns the full props array, with entity sentinel values replaced by their resolved
     * entities and all other values forwarded unchanged.
     *
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function resolveEntityProperties(array $properties, SalesChannelContext $context): array
    {
        $resolved = [];

        foreach ($properties as $key => $value) {
            $resolved[$key] = match ($value) {
                'product' => $this->resolveProductProperty($context),
                'media' => $this->resolveMediaProperty($context),
                default => $value,
            };
        }

        return $resolved;
    }

    private function resolveProductProperty(SalesChannelContext $context): ?SalesChannelProductEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $criteria->addAssociation('media.media');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('options.group');

        $result = $this->productRepository->search($criteria, $context);

        $entity = $result->getEntities()->first();

        return $entity instanceof SalesChannelProductEntity ? $entity : null;
    }

    private function resolveMediaProperty(SalesChannelContext $context): ?MediaEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        // Filter for JPEG and PNG MIME types only using OR condition
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('mimeType', 'image/jpeg'),
            new EqualsFilter('mimeType', 'image/png'),
        ]));

        $result = $this->mediaRepository->search($criteria, $context->getContext());

        $entity = $result->getEntities()->first();

        return $entity instanceof MediaEntity ? $entity : null;
    }

    private function getFirstAvailableSalesChannel(): SalesChannelEntity
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();

        if (!$salesChannel instanceof SalesChannelEntity) {
            throw SalesChannelException::salesChannelNotFound('');
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

            return $this->themeRuntimeConfigStorage->getThemeIdByTechnicalName($themeName);
        }

        return null;
    }
}
