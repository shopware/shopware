<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\RateLimiter\Policy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoff;

/**
 * @internal
 */
class TimeBackoffTest extends TestCase
{
    public function testThrowsExceptionOnInvalidLimits(): void
    {
        $backoff = new TimeBackoff('test', [
            [
                'limit' => 3,
                'interval' => '10 seconds',
            ],
            [
                'limit' => 5,
                'interval' => '30 seconds',
            ],
        ]);

        $reflection = new \ReflectionClass($backoff);
        $stringLimits = $reflection->getProperty('stringLimits');
        $stringLimits->setAccessible(true);
        $stringLimits->setValue($backoff, 'invalid');

        static::expectException(\BadMethodCallException::class);
        $backoff->__wakeup();
    }
}
