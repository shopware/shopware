<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Check;

use Shopware\Core\Framework\Log\Package;

/**
 * The last result of a check, with the execution context it ran in and when.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class StoredResult
{
    public function __construct(
        public Result $result,
        public SystemCheckExecutionContext $context,
        public \DateTimeImmutable $checkedAt,
    ) {
    }
}
