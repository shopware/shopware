<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Log\Package;
/**
 * @internal
 * @package core
 */
#[Package('core')]
abstract class AbstractFieldResolver
{
    abstract public function join(FieldResolverContext $context): string;
}
