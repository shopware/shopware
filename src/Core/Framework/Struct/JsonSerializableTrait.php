<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

trait JsonSerializableTrait
{
    public function jsonSerialize(): array
    {
        $data = [
            '_class' => get_class($this),
        ];

        $vars = get_object_vars($this);
        foreach ($vars as $property => $value) {
            if ($value instanceof \DateTime) {
                $value = $value->format(\DateTime::ATOM);
            }

            $data[$property] = $value;
        }

        return $data;
    }
}
