<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Path\Strategy;

use Shopware\Core\Content\Media\Domain\Path\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal Factory is only used for DI container construction to find configured strategy
 */
#[Package('content')]
class PathStrategyFactory
{
    /**
     * @internal
     *
     * @param AbstractMediaPathStrategy[] $strategies
     */
    public function __construct(private readonly iterable $strategies, private readonly AbstractMediaPathStrategy $bc)
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

        if (Feature::isActive('v6.6.0.0')) {
            throw MediaException::strategyNotFound($strategyName);
        }

        return $this->bc;
    }
}
