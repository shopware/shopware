<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
trait CreateFromTrait
{
    public static function createFrom(Struct $object): static
    {
        try {
            $self = (new \ReflectionClass(static::class))
                ->newInstanceWithoutConstructor();
        } catch (\ReflectionException $exception) {
            throw StructException::createFromError($exception->getMessage());
        }

        $objectVariables = get_object_vars($object);
        if (method_exists($self, 'assign')) {
            $self->assign($objectVariables);

            return $self;
        }

        foreach ($objectVariables as $property => $value) {
            try {
                // @phpstan-ignore property.dynamicName (We have to allow dynamic properties here to copy all variables)
                $self->$property = $value;
            } catch (\TypeError $error) {
                if (Feature::isActive('v6.8.0.0')) {
                    /** @phpstan-ignore shopware.domainException (If trait is used directly, PHPStan complains about the wrong domain) */
                    throw StructException::assignTypeError($error);
                }

                Feature::triggerDeprecationOrThrow(
                    'v6.8.0.0',
                    'Assign will fail with next major: ' . $error->getMessage(),
                    '6.7.13.0'
                );
            } catch (\Throwable) {
            }
        }

        return $self;
    }
}
