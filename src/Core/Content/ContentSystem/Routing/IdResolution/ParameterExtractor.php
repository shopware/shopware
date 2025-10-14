<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Content\ContentSystem\Routing\Router\RouteMatchResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
class ParameterExtractor
{
    public function extract(RouteMatchResult $match): ExtractedParameters
    {
        $route = $match->route;
        $parameters = $match->parameters;
        $parameterBindings = $route->getParameterBindings();

        $resolution = [];
        $passthrough = [];

        foreach ($parameterBindings as $paramName => $binding) {
            $value = $parameters[$paramName] ?? null;

            if ($value === null) {
                continue;
            }

            $placeholder = $binding->getPlaceholder();

            if ($binding->isResolutionParameter() && $binding->resolution !== null) {
                $resolution[$paramName] = new ResolutionParameter($placeholder, $binding->resolution, $value);
                continue;
            }

            $passthrough[$placeholder] = $value;
        }

        return new ExtractedParameters(
            new ResolutionParameterMap($resolution),
            new ParameterMap($passthrough)
        );
    }
}
