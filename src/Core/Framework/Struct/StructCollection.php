<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

class StructCollection extends Collection
{
    /**
     * @var Struct[]
     */
    protected $elements = [];

    public function add(Struct $struct, $key = null): void
    {
        if ($key !== null) {
            $this->elements[$key] = $struct;
        } else {
            $this->elements[] = $struct;
        }
    }

    public function fill(array $elements): void
    {
        foreach ($elements as $key => $element) {
            $this->add($element, $key);
        }
    }

    public function removeByKey($key): void
    {
        $this->doRemoveByKey($key);
    }

    public function get($key): ?Struct
    {
        return $this->elements[$key] ?? null;
    }
}
