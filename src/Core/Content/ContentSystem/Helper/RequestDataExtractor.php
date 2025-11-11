<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Helper;

use Shopware\Core\Content\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts request data for ContentSystem placeholder values.
 *
 * Extracts parameters from both query string and request body, then applies
 * parameter binding configuration to map parameter names to placeholder names.
 *
 * @internal
 */
#[Package('discovery')]
class RequestDataExtractor
{
    /**
     * Merges request body and query parameters (query takes precedence).
     *
     * @param array<string, ParameterBinding>|null $bindings Parameter name mappings (null = pass through all)
     *
     * @return array<string, bool|float|int|string>
     */
    public function extractData(Request $request, ?array $bindings): array
    {
        $requestParameters = array_merge(
            $request->request->all(),
            $request->query->all()
        );

        return $this->applyParameterBindings($bindings, $requestParameters);
    }

    /**
     * Parameters pass through unchanged if no bindings configured.
     * Only scalar values are included (non-scalar values are skipped).
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
            if (!isset($scalarParameters[$paramName])) {
                continue;
            }

            $placeholder = $binding->placeholder ?? $paramName;
            $result[$placeholder] = $scalarParameters[$paramName];
        }

        return $result;
    }
}
