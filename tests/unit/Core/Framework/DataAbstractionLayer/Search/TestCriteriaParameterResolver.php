<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaParameterResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test implementation of CriteriaParameterResolver that delegates to request parameters.
 * This is used in tests to make the resolver transparent and return data from the request.
 */
class TestCriteriaParameterResolver extends CriteriaParameterResolver
{
    public function __construct()
    {
        // Don't call parent constructor to avoid dependency on CompressedCriteriaDecoder
    }

    public function getParameter(Request $request, string $key, mixed $default = null): mixed
    {
        // For tests, simply delegate to request->get() to maintain existing test behavior
        return $request->get($key, $default);
    }
}
