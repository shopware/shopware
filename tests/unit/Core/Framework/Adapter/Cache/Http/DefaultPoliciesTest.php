<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\DefaultPolicies;

/**
 * @internal
 */
#[CoversClass(DefaultPolicies::class)]
class DefaultPoliciesTest extends TestCase
{
    public function testFromArray(): void
    {
        $data = [
            'cacheable' => 'my_cacheable',
            'uncacheable' => 'my_uncacheable',
        ];

        $defaults = DefaultPolicies::fromArray($data);

        static::assertSame('my_cacheable', $defaults->cacheablePolicyName);
        static::assertSame('my_uncacheable', $defaults->uncacheablePolicyName);
    }

    public function testFromArrayDefaults(): void
    {
        $defaults = DefaultPolicies::fromArray([]);

        static::assertNull($defaults->cacheablePolicyName);
        static::assertNull($defaults->uncacheablePolicyName);
    }
}
