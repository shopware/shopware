<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\RateLimiter;

use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;

trait RateLimiterTestTrait
{
    use IntegrationTestBehaviour;

    /**
     * @param array<string, int> $factories
     */
    private function mockResetLimiter(array $factories): RateLimiter
    {
        $rateLimiter = new RateLimiter();

        foreach ($factories as $factory => $expects) {
            $limiter = $this->createMock(LimiterInterface::class);
            $limiter->method('consume')->willReturn(new RateLimit(1, new \DateTimeImmutable(), true, 1));
            $limiter->expects($this->exactly($expects))->method('reset');

            $limiterFactory = $this->createMock(RateLimiterFactory::class);
            $limiterFactory->method('create')->willReturn($limiter);

            $rateLimiter->registerLimiterFactory($factory, $limiterFactory);
        }

        return $rateLimiter;
    }

    private function clearCache(): void
    {
        $this->getContainer()->get('cache.rate_limiter')->clear();
    }
}
