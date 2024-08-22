<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\RateLimiter\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\RateLimiter\Policy\TimeBackoff;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

/**
 * @internal
 */
#[CoversClass(TimeBackoff::class)]
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


        $stringLimits = ReflectionHelper::getProperty(TimeBackoff::class, 'stringLimits');
        $stringLimits->setValue($backoff, 'invalid');

        static::expectException(\BadMethodCallException::class);
        $backoff->__wakeup();
    }
}
