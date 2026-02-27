<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Helper;

use Shopware\Core\Framework\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
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
        $scalarParameters = array_filter($request->query->all(), '\is_scalar');

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
