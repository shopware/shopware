<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

trait JsonSerializableTrait
{
    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);
        foreach ($vars as $property => $value) {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(\DateTime::ATOM);
            }

            $vars[$property] = $value;
        }

        return $vars;
    }
}
