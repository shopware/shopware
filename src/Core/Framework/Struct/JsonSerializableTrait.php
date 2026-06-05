<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
trait JsonSerializableTrait
{
    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        // lazy ghost entities of partial loads: only serialize the loaded properties
        $vars = LazyObjectVars::isUninitialized($this) ? LazyObjectVars::extract($this) : get_object_vars($this);
        $this->convertDateTimePropertiesToJsonStringRepresentation($vars);

        return $vars;
    }

    /**
     * @param array<string, mixed> $array
     */
    protected function convertDateTimePropertiesToJsonStringRepresentation(array &$array): void
    {
        foreach ($array as &$value) {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(\DateTime::RFC3339_EXTENDED);
            }
        }
    }
}
