<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

/**
 * @internal
 */
abstract class AbstractFieldResolver
{
    abstract public function join(FieldResolverContext $context): string;
}
