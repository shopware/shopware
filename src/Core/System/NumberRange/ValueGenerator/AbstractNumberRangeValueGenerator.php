<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractNumberRangeValueGenerator
{
    /**
     * Generates a new value while taking care of states, events and connectors.
     */
    abstract public function getValue(string $type, Context $context, ?string $salesChannelId, bool $preview = false): string;

    /**
     * Generates a preview for a given pattern and start.
     *
     * @deprecated tag:v6.8.0 - use previewPatternByNumberRangeId() with a concrete number range id instead
     */
    abstract public function previewPattern(string $definition, ?string $pattern, int $start): string;

    /**
     * Generates a preview for a persisted number range without mutating its state.
     */
    abstract public function previewPatternByNumberRangeId(string $numberRangeId, ?string $pattern = null, ?int $start = null): string;

    abstract protected function getDecorated(): self;
}
