<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * A requirement that must be met before a service's permissions can be granted.
 */
#[Package('framework')]
interface ServiceRequirement
{
    public static function getName(): string;

    public function isSatisfied(): bool;
}
