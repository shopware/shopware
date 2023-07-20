<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Implementation;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaPathStrategy;
use Shopware\Core\Framework\Log\Package;

#[Package('content')]
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
