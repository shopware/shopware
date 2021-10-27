<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system
 *
 * @method void                        add(AppScriptEntity $entity)
 * @method void                        set(string $key, AppScriptEntity $entity)
 * @method \Generator<AppScriptEntity> getIterator()
 * @method array<AppScriptEntity>      getElements()
 * @method AppScriptEntity|null        get(string $key)
 * @method AppScriptEntity|null        first()
 * @method AppScriptEntity|null        last()
 */
class AppScriptCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppScriptEntity::class;
    }
}
