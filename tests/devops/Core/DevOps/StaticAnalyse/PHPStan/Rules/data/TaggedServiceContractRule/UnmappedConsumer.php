<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule;

class UnmappedConsumer
{
    /**
     * @param iterable<UnmappedContract> $services
     */
    public function __construct(iterable $services)
    {
    }
}
