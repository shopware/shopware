<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule;

use Symfony\Contracts\Service\ServiceProviderInterface;

class LocatorConsumer
{
    /**
     * @param ServiceProviderInterface<Contract> $services
     */
    public function __construct(ServiceProviderInterface $services)
    {
    }
}
