<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

trait CreateFromTrait
{
    public static function createFrom(Struct $object)
    {
        try {
            $self = (new \ReflectionClass(static::class))
                ->newInstanceWithoutConstructor();
        } catch (\ReflectionException $exception) {
            throw new \InvalidArgumentException($exception->getMessage());
        }

        foreach (get_object_vars($object) as $property => $value) {
            $self->$property = $value;
        }

        return $self;
    }
}
