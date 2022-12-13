<?php declare(strict_types=1);

namespace Shopware\Recovery\Update\Struct;

/**
 * @package system-settings
 */
abstract class Struct
{
    public function __construct(array $values = [])
    {
        foreach ($values as $name => $value) {
            $this->$name = $value;
        }
    }
}
