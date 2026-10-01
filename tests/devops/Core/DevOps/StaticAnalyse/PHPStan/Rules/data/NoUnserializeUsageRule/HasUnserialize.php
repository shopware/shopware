<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoUnserializeUsageRule;

class HasUnserialize
{
    public function unserializeData(string $serialized): array
    {
        $first = unserialize($serialized);
        $second = \unserialize($serialized);

        /**
         * @phpstan-ignore shopware.unserializeUsage
         */
        $third = \unserialize($serialized);

        $this->unserialize();
        $unserialize = 'unserialize';

        return [$first, $second, $third, $unserialize];
    }

    private function unserialize(): void
    {
    }
}
