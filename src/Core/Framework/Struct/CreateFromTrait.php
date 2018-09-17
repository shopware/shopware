<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

/**
 * @category  Shopware\Core
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
trait CreateFromTrait
{
    public static function createFrom(Struct $object)
    {
        $self = new static();
        foreach ($object as $property => $value) {
            $self->$property = $value;
        }

        return $self;
    }
}
