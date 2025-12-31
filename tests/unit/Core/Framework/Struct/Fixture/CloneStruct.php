<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct\Fixture;

use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Tests\Unit\Core\Framework\Struct\CloneStructBackedEnum;
use Shopware\Tests\Unit\Core\Framework\Struct\CloneStructUnitEnum;

/**
 * @internal
 */
class CloneStruct
{
    use CloneTrait;

    /**
     * @var array<array-key, CloneStruct>
     */
    public array $arrayOfStructs;

    public CloneStructBackedEnum $backedEnum;

    public CloneStructUnitEnum $unitEnum;

    public CloneStruct $nestedStruct;
}
