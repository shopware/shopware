<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversPackageMatchRule\Tests;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversPackageMatchRule\Covered\CheckoutHelperTrait;

#[Package('framework')]
#[CoversTrait(CheckoutHelperTrait::class)]
class MismatchedTraitFixture extends TestCase
{
}
