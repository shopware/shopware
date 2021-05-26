<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter;

use Shopware\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;

class NoLimitRateLimiterFactory extends RateLimiterFactory
{
    private RateLimiterFactory $rateLimiterFactory;

    public function __construct(RateLimiterFactory $rateLimiterFactory)
    {
        $this->rateLimiterFactory = $rateLimiterFactory;
    }

    public function create(?string $key = null): LimiterInterface
    {
        if (DisableRateLimiterCompilerPass::isDisabled()) {
            return new NoLimiter();
        }

        return $this->rateLimiterFactory->create($key);
    }
}
