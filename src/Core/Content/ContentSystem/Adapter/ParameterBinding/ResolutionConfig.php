<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\ParameterBinding;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class ResolutionConfig
{
    /**
     * @codeCoverageIgnore
     *
     * @param list<Filter> $constraints
     */
    public function __construct(
        public string $entity,
        public string $matchField,
        public array $constraints = []
    ) {
    }
}
