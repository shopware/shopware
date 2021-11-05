<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system
 *
 * @method void                     add(ScriptEntity $entity)
 * @method void                     set(string $key, ScriptEntity $entity)
 * @method \Generator<ScriptEntity> getIterator()
 * @method array<ScriptEntity>      getElements()
 * @method ScriptEntity|null        get(string $key)
 * @method ScriptEntity|null        first()
 * @method ScriptEntity|null        last()
 */
class ScriptCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ScriptEntity::class;
    }
}
