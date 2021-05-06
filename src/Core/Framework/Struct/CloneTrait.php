<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

trait CloneTrait
{
    public function __clone()
    {
        /** @var array<string, object|array> $variables */
        $variables = get_object_vars($this);
        foreach ($variables as $key => $value) {
            if (\is_object($value)) {
                $this->$key = clone $this->$key;
            } elseif (\is_array($value)) {
                $this->$key = $this->cloneArray($value);
            }
        }
    }

    private function cloneArray(array $array): array
    {
        $newValue = [];

        foreach ($array as $index => $value) {
            if (\is_object($value)) {
                $newValue[$index] = clone $value;
            } elseif (\is_array($value)) {
                $newValue[$index] = $this->cloneArray($value);
            } else {
                $newValue[$index] = $value;
            }
        }

        return $newValue;
    }
}
