<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class InternalFieldAccessNotAllowedException extends \RuntimeException
{
    public function __construct(string $property, Entity $entity)
    {
        parent::__construct(sprintf('Access to property "%s" not allowed on entity "%s".', $property, \get_class($entity)));
    }
}
