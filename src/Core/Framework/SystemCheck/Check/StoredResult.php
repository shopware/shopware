<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Check;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class StoredResult
{
    public function __construct(
        public Result $result,
        public \DateTimeImmutable $checkedAt,
    ) {
    }
}
