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

        $vars = get_object_vars($object);

        foreach ($vars as $property => $value) {
            $self->$property = $value;
        }

        /* @var static $self */
        return $self;
    }
}
