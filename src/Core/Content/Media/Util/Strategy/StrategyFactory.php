<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util\Strategy;

use Shopware\Core\Content\Media\Exception\StrategyNotFoundException;

class StrategyFactory implements StrategyFactoryInterface
{
    /**
     * @var StrategyInterface[]
     */
    private $strategies;

    public function __construct(iterable $strategies)
    {
        $this->strategies = $strategies;
    }

    public function factory(string $strategyName): StrategyInterface
    {
        return $this->findStrategyByName($strategyName);
    }

    /**
     * @throws StrategyNotFoundException
     */
    private function findStrategyByName(string $strategyName): StrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $strategyName) {
                return $strategy;
            }
        }

        throw new StrategyNotFoundException($strategyName);
    }
}
