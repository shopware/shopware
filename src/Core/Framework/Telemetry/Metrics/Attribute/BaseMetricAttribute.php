<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Attribute;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore non instantiable class, covered in child classes
 */
#[Package('core')]
abstract readonly class BaseMetricAttribute implements MetricAttributeInterface
{
    public function __construct(
        private float|int|string|null $value = null,
        private string $type = self::TYPE_VALUE,
    ) {
    }

    protected function getValueFromPropertyOrMethod(object $decorated, string $propertyOrMethodName): mixed
    {
        if (property_exists($decorated, $propertyOrMethodName)) {
            /** @phpstan-ignore-next-line ignoring \Symplify\PHPStanRules\Rules\NoDynamicNameRule, as we need to have it dynamic */
            $value = $decorated->{$propertyOrMethodName};

            return \is_callable($value) ? \call_user_func($value) : $value;
        }

        if (method_exists($decorated, $propertyOrMethodName)) {
            /** @phpstan-ignore-next-line ignoring \Symplify\PHPStanRules\Rules\NoDynamicNameRule, as we need to have it dynamic */
            return $decorated->{$propertyOrMethodName}();
        }

        return null;
    }

    protected function getValue(object $decorated): mixed
    {
        if ($this->type === self::TYPE_DYNAMIC && \is_string($this->value)) {
            return $this->getValueFromPropertyOrMethod($decorated, $this->value);
        }

        return $this->value;
    }
}
