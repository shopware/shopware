<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\AppUrlChangeResolver;

use Shopware\Core\Framework\App\Exception\AppUrlChangeStrategyNotFoundException;
use Shopware\Core\Framework\App\Exception\NoAppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Resolver
{
    /**
     * @var iterable|AbstractAppUrlChangeStrategy[]
     */
    private $strategies;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @param AbstractAppUrlChangeStrategy[] $strategies
     */
    public function __construct(iterable $strategies, SystemConfigService $systemConfigService)
    {
        $this->strategies = $strategies;
        $this->systemConfigService = $systemConfigService;
    }

    public function resolve(string $strategyName, Context $context): void
    {
        if (!$this->systemConfigService->get(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY)) {
            throw new NoAppUrlChangeDetectedException();
        }

        /** @var AbstractAppUrlChangeStrategy $strategy */
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $strategyName) {
                $strategy->resolve($context);

                return;
            }
        }

        throw new AppUrlChangeStrategyNotFoundException($strategyName);
    }

    /**
     * @return string[]
     */
    public function getAvailableStrategies(): array
    {
        $strategies = [];

        /** @var AbstractAppUrlChangeStrategy $strategy */
        foreach ($this->strategies as $strategy) {
            $strategies[$strategy->getName()] = $strategy->getDescription();
        }

        return $strategies;
    }
}
