<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Content\ContentSystem\Routing\Struct\RouteMatchResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ResolutionItem array{placeholder: string, resolution: array<string, mixed>, value: mixed}
 * @phpstan-type ResolutionParameterMap array<string, ResolutionItem>
 * @phpstan-type PassthroughParameterMap array<string, mixed>
 * @phpstan-type ExtractedParameters array{resolution: ResolutionParameterMap, passthrough: PassthroughParameterMap}
 *
 * @final
 */
#[Package('discovery')]
class ParameterExtractor
{
    /**
     * @return ExtractedParameters
     */
    public function extract(RouteMatchResult $match): array
    {
        $route = $match->getRoute();
        $parameters = $match->getParameters();
        $parameterBinding = $route->getParameterBinding();

        $resolution = [];
        $passthrough = [];

        foreach ($parameterBinding as $paramName => $config) {
            $value = $parameters[$paramName] ?? null;

            if ($value === null) {
                continue;
            }

            $placeholder = $config['placeholder'] ?? $paramName;

            if (isset($config['resolution'])) {
                $resolution[$paramName] = [
                    'placeholder' => $placeholder,
                    'resolution' => $config['resolution'],
                    'value' => $value,
                ];
            } else {
                $passthrough[$placeholder] = $value;
            }
        }

        return [
            'resolution' => $resolution,
            'passthrough' => $passthrough,
        ];
    }
}
