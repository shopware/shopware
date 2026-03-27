<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractFkResolver
{
    /**
     * Returns the unique name for the resolver which is used to identify for fk resolving hash map
     */
    abstract public static function getName(): string;

    /**
     * @param list<FkReference> $map
     *
     * @throws EntityNotFoundException
     *
     * @return list<FkReference>
     */
    abstract public function resolve(array $map): array;
}
