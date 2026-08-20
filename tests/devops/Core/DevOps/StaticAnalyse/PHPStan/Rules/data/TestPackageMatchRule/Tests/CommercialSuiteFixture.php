<?php declare(strict_types=1);

namespace Shopware\Commercial\Tests\Unit\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule\Covered\CheckoutService;

#[Package('framework')]
#[CoversClass(CheckoutService::class)]
class CommercialSuiteFixture extends TestCase
{
}
