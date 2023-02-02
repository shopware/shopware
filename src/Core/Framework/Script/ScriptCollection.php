<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<ScriptEntity>
 */
class ScriptCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ScriptEntity::class;
    }
}
