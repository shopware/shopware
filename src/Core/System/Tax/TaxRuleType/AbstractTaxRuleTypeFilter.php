<?php

declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxRuleType;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;

#[Package('checkout')]
abstract class AbstractTaxRuleTypeFilter implements TaxRuleTypeFilterInterface
{
    public function __construct(
        protected readonly ClockInterface $clock,
    ) {
    }

    protected function isTaxActive(TaxRuleEntity $taxRuleEntity): bool
    {
        return $taxRuleEntity->getActiveFrom() < \DateTime::createFromImmutable($this->clock->now())->setTimezone(new \DateTimeZone('UTC'));
    }
}
