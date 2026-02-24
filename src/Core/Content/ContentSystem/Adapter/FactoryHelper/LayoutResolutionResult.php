<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignmentInterface;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\Log\Package;

/**
 * Value object containing the result of layout resolution.
 *
 * @internal
 */
#[Package('discovery')]
final readonly class LayoutResolutionResult
{
    public function __construct(
        public ContentLayoutAssignmentInterface $assignment,
        public PlaceholderValues $placeholderValues
    ) {
    }
}
