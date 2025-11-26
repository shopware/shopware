<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Helper;

use Shopware\Core\Content\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts query parameters from request and maps them to ContentSystem placeholder values.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class RequestDataExtractor
{
    /**
     * Extracts query parameters from request.
     *
     * @param array<string, ParameterBinding>|null $bindings Parameter name mappings (null = pass through all)
     *
     * @return array<string, bool|float|int|string>
     */
    public function extractData(Request $request, ?array $bindings): array
    {
        return $this->applyParameterBindings($bindings, $request->query->all());
    }

    /**
     * Parameters pass through unchanged if no bindings configured
     * Only scalar values are included (non-scalar values are skipped)
     *
     * @param array<string, ParameterBinding>|null $bindings
     * @param array<string, mixed> $requestParameters
     *
     * @return array<string, bool|float|int|string>
     */
    private function applyParameterBindings(?array $bindings, array $requestParameters): array
    {
        $scalarParameters = array_filter($requestParameters, function ($value) {
            return \is_scalar($value);
        });

        if ($bindings === null || $bindings === []) {
            return $scalarParameters;
        }

        $result = [];
        foreach ($bindings as $paramName => $binding) {
            if (!\array_key_exists($paramName, $scalarParameters)) {
                continue;
            }

            $placeholder = $binding->placeholder ?? $paramName;
            $result[$placeholder] = $scalarParameters[$paramName];
        }

        return $result;
    }
}
