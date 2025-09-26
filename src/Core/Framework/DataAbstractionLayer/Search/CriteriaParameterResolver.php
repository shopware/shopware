<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service for resolving request parameters from either _criteria or direct request parameters.
 * Ensures parameters are parsed only once per request and cached in request attributes.
 * To be used for working with parameters described in Criteria and ProductListingCriteria OpenAPI spec.
 */
#[Package('framework')]
class CriteriaParameterResolver
{
    public const ATTRIBUTE_RESOLVED_CRITERIA = 'sw_resolved_criteria';

    /**
     * @internal
     */
    public function __construct(
        private readonly CompressedCriteriaDecoder $criteriaDecoder
    ) {
    }

    /**
     * Get parameter by key:
     * - If request method is GET and _criteria parameter is present, return value from decoded _criteria parameters
     * - Otherwise, return value from $request->get(...)
     * - If parameter is not present in either, return $default
     */
    public function getParameter(Request $request, string $key, mixed $default = null): mixed
    {
        if ($request->isMethod(Request::METHOD_GET)) {
            $parameters = $this->getCompressedCriteriaParameters($request);
            if ($parameters !== null) {
                return $parameters[$key] ?? $default;
            }
        }

        return $request->get($key, $default);
    }

    /**
     * Get resolved and cached _criteria parameters from the request, if available, or resolve and cache them
     * in the request attributes. Should return null if no _criteria parameter is present (or method is not GET).
     *
     * @return array<string, mixed>|null
     */
    private function getCompressedCriteriaParameters(Request $request): ?array
    {
        // Check if already resolved and cached in request attributes
        if ($request->attributes->has(self::ATTRIBUTE_RESOLVED_CRITERIA)) {
            $parameters = $request->attributes->get(self::ATTRIBUTE_RESOLVED_CRITERIA);
        } else {
            $parameters = $this->resolveParameters($request);
            $request->attributes->set(self::ATTRIBUTE_RESOLVED_CRITERIA, $parameters);
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveParameters(Request $request): ?array
    {
        if ($request->isMethod(Request::METHOD_GET)) {
            // Check for _criteria parameter first
            if ($request->query->has('_criteria')) {
                return $this->criteriaDecoder->decode(
                    (string) $request->query->get('_criteria')
                );
            }
        }

        return null;
    }
}
