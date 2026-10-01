<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoUnserializeUsageRule;

use PHPUnit\Framework\TestCase;

class HasUnserializeInTestClass extends TestCase
{
    public function testSomething(string $serialized): mixed
    {
        return \unserialize($serialized);
    }

    public function testSomethingSneaky(string $serialized): mixed
    {
        /**
         * @phpstan-ignore shopware.unserializeUsage
         */
        return \unserialize($serialized);
    }
}
