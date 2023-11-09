<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - reason:factory-for-deprecation - Use PathStrategyFactory instead
 */
#[Package('buyers-experience')]
class StrategyFactory
{
    /**
     * @internal
     *
     * @param PathnameStrategyInterface[] $strategies
     */
    public function __construct(private readonly iterable $strategies)
    {
    }

    public function factory(string $strategyName): PathnameStrategyInterface
    {
        return $this->findStrategyByName($strategyName);
    }

    /**
     * @throws MediaException
     */
    private function findStrategyByName(string $strategyName): PathnameStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $strategyName) {
                return $strategy;
            }
        }

        throw MediaException::strategyNotFound($strategyName);
    }
}
