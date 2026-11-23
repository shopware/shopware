<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter;

use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\ContentSystem\ResolvedContentLayout;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class RenderingSpecificationFactory
{
    /**
     * @param list<string> $ignoredUrlParameters
     */
    public function __construct(private readonly array $ignoredUrlParameters)
    {
    }

    public function create(
        AbstractSpecificationSource $source,
        string $path,
        Request $request,
        SalesChannelContext $context,
    ): ResolvedContentLayout {
        $layoutId = $source->resolveLayoutId($path, $request, $context);
        $data = $source->resolveSpecificationData($path, $request, $context);

        return ResolvedContentLayout::create(
            $layoutId,
            new RenderingSpecification(
                dataRequirements: $data->dataRequirements,
                placeholderValues: $this->placeholderValues($source, $data->placeholderValues, $request),
                request: $request,
                targetElementId: $source->resolveTargetElementId($path, $request, $context),
                cacheTags: $source->resolveCacheTags($path, $request, $context),
                rootSource: $source->rootSource(),
            ),
        );
    }

    public function createWithoutLayout(
        AbstractSpecificationSource $source,
        string $entityId,
        Request $request,
        SalesChannelContext $context,
    ): RenderingSpecification {
        $data = $source->resolveSpecificationDataForEntity($entityId, $request, $context);

        return new RenderingSpecification(
            dataRequirements: $data->dataRequirements,
            placeholderValues: $this->placeholderValues($source, $data->placeholderValues, $request),
            request: $request,
            targetElementId: $source->resolveTargetElementId('', $request, $context),
            cacheTags: [],
            rootSource: $source->rootSource(),
        );
    }

    private function placeholderValues(AbstractSpecificationSource $source, PlaceholderValues $derived, Request $request): PlaceholderValues
    {
        $query = $request->query->all();
        $values = [];

        // A value excluded from the HTTP cache key must not influence the rendered content.
        foreach (array_diff($source->placeholderQueryParameters(), $this->ignoredUrlParameters) as $name) {
            if (\is_scalar($query[$name] ?? null)) {
                $values[$name] = $query[$name];
            }
        }

        if ($values === []) {
            return $derived;
        }

        return PlaceholderValues::from(array_replace($values, $derived->all()));
    }
}
