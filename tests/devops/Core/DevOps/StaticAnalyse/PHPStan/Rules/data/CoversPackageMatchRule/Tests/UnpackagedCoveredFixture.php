<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversPackageMatchRule\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversPackageMatchRule\Covered\UnpackagedService;

#[Package('framework')]
#[CoversClass(UnpackagedService::class)]
class UnpackagedCoveredFixture extends TestCase
{
}
