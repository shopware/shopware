<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\DataAbstractionLayer\Contract\IdAware;
use Shopware\Core\Framework\DataAbstractionLayer\Contract\RuleIdAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * This service filters the given input data, based on the given ruleIds.
 * It will return a filtered array of objects, which have a ruleId that is present in the given ruleIds array.
 *
 * @psalm-type Option =
 */
#[Package('core')]
class RuleIdMatcher
{
    /**
     * @param (IdAware&RuleIdAware)[] $options
     * @param string[] $ruleIds
     *
     * @return (IdAware&RuleIdAware)[]
     */
    public function filter(array $options, array $ruleIds): array
    {
        return \array_values(\array_filter($options, function (IdAware&RuleIdAware $option) use ($ruleIds) {
            return $option->getAvailabilityRuleId() === null || \in_array($option->getAvailabilityRuleId(), $ruleIds, true);
        }));
    }

    /**
     * @phpstan-template T of Collection<covariant (IdAware&RuleIdAware)>
     *
     * @phpstan-param T $options
     *
     * @param string[] $ruleIds
     *
     * @phpstan-return T
     */
    public function filterCollection(Collection $options, array $ruleIds): Collection
    {
        return $options->filter(function (IdAware&RuleIdAware $option) use ($ruleIds) {
            return $option->getAvailabilityRuleId() === null || \in_array($option->getAvailabilityRuleId(), $ruleIds, true);
        });
    }
}
