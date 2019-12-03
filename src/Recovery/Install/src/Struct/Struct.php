<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Struct;

abstract class Struct
{
    public function __construct(array $values = [])
    {
        foreach ($values as $name => $value) {
            $this->$name = $value;
        }
    }
}
