<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversTargetInCoverageSourceRule\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversTargetInCoverageSourceRule\project\src\Boilerplate\ExcludedEntity;

#[CoversClass(ExcludedEntity::class)]
class NonUnitNamespaceFixture extends TestCase
{
}
