<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

/**
 * @package core
 */
class InternalFieldAccessNotAllowedException extends \RuntimeException
{
    public function __construct(string $property, object $entity)
    {
        parent::__construct(sprintf('Access to property "%s" not allowed on entity "%s".', $property, $entity::class));
    }
}
