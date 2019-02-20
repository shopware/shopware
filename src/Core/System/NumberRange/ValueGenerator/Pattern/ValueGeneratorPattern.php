<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

abstract class ValueGeneratorPattern implements ValueGeneratorPatternInterface
{
    protected const PATTERN_ID = '';

    /**
     * @var NumberRangeEntity
     */
    protected $configuration;

    /**
     * @var CheckoutContext
     */
    protected $checkoutContext;

    abstract public function resolve(NumberRangeEntity $configuration, CheckoutContext $checkoutContext, ?array $args = null): string;

    public function getPatternId(): string
    {
        return static::PATTERN_ID;
    }
}
