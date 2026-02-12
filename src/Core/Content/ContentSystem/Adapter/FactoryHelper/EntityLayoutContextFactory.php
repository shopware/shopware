<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignableDefinitionInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignmentInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Helper\RequestDataExtractor;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\SpecificationData;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class EntityLayoutContextFactory
{
    public function __construct(
        private readonly EntityLayoutResolver $layoutResolver,
        private readonly RequestDataExtractor $requestDataExtractor
    ) {
    }

    public function supports(string $path, ContentLayoutAssignableDefinitionInterface $definition): bool
    {
        $path = '/' . ltrim($path, '/');
        $pathPrefix = $definition->getContentLayoutPathPrefix();

        return str_starts_with($path, $pathPrefix);
    }

    /**
     * @param EntityRepository<ProductContentLayoutCollection|CategoryContentLayoutCollection|LandingPageContentLayoutCollection> $repository
     */
    public function resolveLayoutId(
        string $path,
        SalesChannelContext $context,
        EntityRepository $repository,
        ContentLayoutAssignableDefinitionInterface $definition
    ): string {
        $entityId = $this->extractEntityId($path, $definition);

        $layoutId = $this->layoutResolver->findLayoutId(
            $definition->getContentLayoutEntityIdField(),
            $entityId,
            $context,
            $repository
        );

        if ($layoutId === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                $definition->getContentLayoutEntityType(),
                $entityId,
                $context->getSalesChannel()->getId()
            );
        }

        return $layoutId;
    }

    /**
     * @param EntityRepository<ProductContentLayoutCollection|CategoryContentLayoutCollection|LandingPageContentLayoutCollection> $repository
     */
    public function resolveSpecificationData(
        string $path,
        Request $request,
        SalesChannelContext $context,
        EntityRepository $repository,
        ContentLayoutAssignableDefinitionInterface $definition
    ): SpecificationData {
        $entityId = $this->extractEntityId($path, $definition);

        $layoutData = $this->layoutResolver->resolve(
            $entityId,
            $request,
            $context,
            $repository,
            $definition
        );

        $dataRequirements = $this->transformDataRequirements($layoutData->assignment, $context, $definition);

        return new SpecificationData(
            dataRequirements: $dataRequirements,
            placeholderValues: $layoutData->placeholderValues,
        );
    }

    public function resolveTargetElementId(Request $request): ?string
    {
        $params = $this->requestDataExtractor->extractData($request, null);

        if (\array_key_exists('elementId', $params) && \is_string($params['elementId']) && $params['elementId'] !== '') {
            return $params['elementId'];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, ContentLayoutAssignableDefinitionInterface $definition): array
    {
        $entityId = $this->extractEntityId($path, $definition);

        return $definition->getCacheTags($entityId);
    }

    /**
     * @throws ContentSystemException If path doesn't match route pattern
     */
    private function extractEntityId(string $path, ContentLayoutAssignableDefinitionInterface $definition): string
    {
        $path = '/' . ltrim($path, '/');
        $routePattern = $definition->getContentLayoutRoutePattern();
        $pathPrefix = $definition->getContentLayoutPathPrefix();

        $route = new Route($pathPrefix . $routePattern);
        $collection = new RouteCollection();
        $collection->add('entity', $route);

        $requestContext = new RequestContext();
        $requestContext->setPathInfo($path);

        $matcher = new UrlMatcher($collection, $requestContext);

        try {
            $parameters = $matcher->match($path);
        } catch (ResourceNotFoundException) {
            $expectedFormat = $definition->getContentLayoutPathPrefix() . $definition->getContentLayoutRoutePattern();
            throw ContentSystemException::invalidEntityPath(
                $definition->getContentLayoutEntityType(),
                $path,
                $expectedFormat
            );
        }

        $entityIdField = $definition->getContentLayoutEntityIdField();

        return $parameters[$entityIdField];
    }

    /**
     * When bindings remap property names (productId -> product_id),
     * data requirements must reference the remapped placeholder names.
     *
     * @return list<DataRequirement>
     */
    private function transformDataRequirements(
        ContentLayoutAssignmentInterface $assignment,
        SalesChannelContext $context,
        ContentLayoutAssignableDefinitionInterface $definition
    ): array {
        $requirements = $definition->getPageDataRequirements($context);
        $bindings = $assignment->getParameterBindings();

        if ($bindings === null || $bindings === []) {
            return array_values($requirements);
        }

        $fieldToPlaceholder = [];
        foreach ($bindings as $fieldName => $binding) {
            $placeholder = $binding->placeholder ?? $fieldName;
            if ($placeholder === '') {
                continue;
            }
            $fieldToPlaceholder[$fieldName] = $placeholder;
        }

        $transformed = [];
        foreach ($requirements as $requirement) {
            if ($requirement->source !== EntityLoader::SOURCE) {
                $transformed[] = $requirement;
                continue;
            }

            $config = $requirement->config;
            if (!($config instanceof EntityLoaderConfig)) {
                $transformed[] = $requirement;
                continue;
            }

            $propertyName = $config->property;
            if (!\array_key_exists($propertyName, $fieldToPlaceholder)) {
                $transformed[] = $requirement;
                continue;
            }

            $newConfig = new EntityLoaderConfig(
                $config->entity,
                $fieldToPlaceholder[$propertyName],
                $config->associations
            );

            $transformed[] = new DataRequirement($requirement->key, $requirement->source, $newConfig);
        }

        return $transformed;
    }
}
