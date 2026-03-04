<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\Log\Package;

/**
 * Value object containing the result of layout resolution.
 *
 * @internal
 */
#[Package('framework')]
final readonly class LayoutResolutionResult
{
    public function __construct(
        public AbstractContentLayoutAssignmentEntity $assignment,
        public PlaceholderValues $placeholderValues
    ) {
    }
}
