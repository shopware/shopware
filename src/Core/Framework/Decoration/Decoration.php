<?php

namespace Shopware\Core\Framework\Decoration;

use Symfony\Contracts\EventDispatcher\Event;

abstract class Decoration extends Event
{
    abstract public static function name(): string;

    final public static function pre(): string
    {
        return static::name() . '.pre';
    }

    final public static function post(): string
    {
        return static::name() . '.post';
    }

    abstract public function result();

    public mixed $result = null;
}
