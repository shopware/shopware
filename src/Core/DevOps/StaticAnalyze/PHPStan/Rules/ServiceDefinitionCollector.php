<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ServiceDefinitionCollectorData array{serviceId: string}
 *
 * @implements Collector<Class_, list<ServiceDefinitionCollectorData>>
 *
 * @internal
 */
#[Package('framework')]
class ServiceDefinitionCollector implements Collector
{
    public function __construct(
        private readonly ServiceMap $serviceMap
    ) {
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     *
     * @return list<ServiceDefinitionCollectorData>|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if ($node->namespacedName === null) {
            return null;
        }

        $class = $node->namespacedName->toString();
        $service = $this->serviceMap->getService($class);

        if ($service === null || $service->getAlias() !== null) {
            return null;
        }

        return [
            [
                'serviceId' => $service->getId(),
            ],
        ];
    }
}
