<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversAttributeRule\Unit;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\CloneTrait;

#[CoversTrait(CloneTrait::class)]
class CoversTraitFixture extends TestCase
{
}
