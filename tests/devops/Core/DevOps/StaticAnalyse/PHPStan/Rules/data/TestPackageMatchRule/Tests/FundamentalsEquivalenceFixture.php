<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule\Covered\FundamentalsFrameworkService;

#[Package('framework')]
#[CoversClass(FundamentalsFrameworkService::class)]
class FundamentalsEquivalenceFixture extends TestCase
{
}
