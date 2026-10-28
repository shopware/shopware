<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\ProductAssignment;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final readonly class SalesChannelProductAssignmentResult
{
    /**
     * @param int $assigned the number of products that received a new visibility in this call
     * @param bool $complete false when the page budget ran out and the same call has to be repeated to assign the remaining products
     */
    public function __construct(
        public int $assigned,
        public bool $complete,
    ) {
    }
}
