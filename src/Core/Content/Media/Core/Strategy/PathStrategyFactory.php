<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Strategy;

use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal Factory is only used for DI container construction to find configured strategy
 */
#[Package('buyers-experience')]
class PathStrategyFactory
{
    /**
     * @internal
     *
     * @param AbstractMediaPathStrategy[] $strategies
     */
    public function __construct(private readonly iterable $strategies)
    {
    }

    public function factory(string $strategyName): AbstractMediaPathStrategy
    {
        return $this->findStrategyByName($strategyName);
    }

    /**
     * @throws MediaException
     */
    private function findStrategyByName(string $strategyName): AbstractMediaPathStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->name() === $strategyName) {
                return $strategy;
            }
        }

        throw MediaException::strategyNotFound($strategyName);
    }
}
