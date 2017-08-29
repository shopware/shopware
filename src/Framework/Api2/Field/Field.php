<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Field;

use Shopware\Framework\Api2\ApiFlag\Flag;

abstract class Field
{
    /**
     * @var Flag[]
     */
    private $flags = [];

    abstract public function __invoke(string $type, string $key, $value = null): \Generator;

    /**
     * @param Flag[] ...$flags
     * @return self
     */
    public function setFlags(Flag  ...$flags): Field
    {
        $this->flags = $flags;

        return $this;
    }

    /**
     * @param string $class
     * @return bool
     */
    public function is(string $class) :bool
    {
        foreach ($this->flags as $flag) {
            if($flag instanceof $class) {
                return true;
            }
        }

        return false;
    }
}