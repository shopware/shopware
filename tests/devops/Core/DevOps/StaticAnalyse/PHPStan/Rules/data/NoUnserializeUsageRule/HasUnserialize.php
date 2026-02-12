<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoUnserializeUsageRule;

class HasUnserialize
{
    public function unserializeData(string $serialized): array
    {
        $first = unserialize($serialized);
        $second = \unserialize($serialized);

        return [$first, $second];
    }
}
