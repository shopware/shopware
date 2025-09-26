<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaParameterResolver;

/**
 * This class is only for unit tests to simplify creating a CriteriaParameterResolver with its dependencies.
 *
 * @internal
 */
class CriteriaParameterResolverFactory
{
    public static function createCriteriaParameterResolver(): CriteriaParameterResolver
    {
        return new CriteriaParameterResolver(
            new CompressedCriteriaDecoder()
        );
    }
}
