<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Extension;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Determination of a number range value
 *
 * @description This event allows interception of the number range value generation to modify the generated value.
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<string>
 */
#[Package('framework')]
final class NumberRangeValueGeneratorExtension extends Extension
{
    public const NAME = 'number-range-value-generator';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        public readonly string $type,
        public readonly Context $context,
        public readonly ?string $salesChannelId,
        public readonly bool $preview,
    ) {
    }
}
