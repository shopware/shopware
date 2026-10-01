<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct\Fixture;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<AssignTestStruct>
 */
class AssignTestCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return AssignTestStruct::class;
    }
}
