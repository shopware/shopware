<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\MessagesShouldNotUsePHPStanTypes;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

class AsyncMessageUsingNativeTypes implements AsyncMessageInterface
{
    /**
     * @param array<int, array<string, string>> $primaryKeys
     * @param non-negative-int $quantity
     * @param class-string $className
     */
    public function __construct(
        public readonly array $primaryKeys,
        public readonly int $quantity,
        public readonly string $className,
    ) {
    }
}
