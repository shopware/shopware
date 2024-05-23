<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Contract;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
interface RuleIdAware
{
    public function getAvailabilityRuleId(): ?string;
}
