<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule;

class WrongMappedConsumer
{
    /**
     * @param iterable<WrongContract> $services
     */
    public function __construct(iterable $services)
    {
    }
}
