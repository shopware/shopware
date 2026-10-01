<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - use AbstractNumberRangeValueGenerator instead
 */
#[Package('framework')]
interface NumberRangeValueGeneratorInterface
{
    /**
     * generates a new Value while taking Care of States, Events and Connectors
     */
    public function getValue(string $type, Context $context, ?string $salesChannelId, bool $preview = false): string;

    /**
     * generates a preview for a given pattern and start
     *
     * @deprecated tag:v6.8.0 - use the number-range-id based Admin API preview route instead
     */
    public function previewPattern(string $definition, ?string $pattern, int $start): string;
}
