<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use DataAbstractionLayerException::internalFieldAccessNotAllowed instead
 */
#[Package('core')]
class InternalFieldAccessNotAllowedException extends \RuntimeException
{
    public function __construct(
        string $property,
        object $entity
    ) {
        parent::__construct(\sprintf('Access to property "%s" not allowed on entity "%s".', $property, $entity::class));
    }
}
