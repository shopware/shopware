<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation\Fixtures;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class DefinitionWithInheritedFlagWithoutInheritanceStub extends DefinitionWithInheritedAssociationsStub
{
    public function isInheritanceAware(): bool
    {
        return false;
    }
}
