<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversPackageMatchRule\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversPackageMatchRule\Covered\CheckoutService;

#[CoversClass(CheckoutService::class)]
class MissingTestPackageFixture extends TestCase
{
}
