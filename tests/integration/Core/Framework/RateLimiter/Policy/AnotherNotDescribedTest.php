<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\RateLimiter\Policy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AnotherNotDescribedTest extends TestCase
{
    public function testNothing(): void
    {
        static::markTestSkipped('');
    }
}
