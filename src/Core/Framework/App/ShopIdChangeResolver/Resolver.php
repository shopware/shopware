<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class Resolver
{
    /**
     * @param AbstractShopIdChangeStrategy[] $strategies
     */
    public function __construct(
        private iterable $strategies
    ) {
    }

    public function resolve(string $strategyName, Context $context): void
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $strategyName) {
                $strategy->resolve($context);

                return;
            }
        }

        throw AppException::shopIdChangeResolveStrategyNotFound($strategyName);
    }

    /**
     * @return array<string>
     */
    public function getAvailableStrategies(): array
    {
        $strategies = [];

        foreach ($this->strategies as $strategy) {
            $strategies[$strategy->getName()] = $strategy->getDescription();
        }

        return $strategies;
    }
}
