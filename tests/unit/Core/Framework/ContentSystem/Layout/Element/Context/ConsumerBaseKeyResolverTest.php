<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerBaseKeyResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConsumerBaseKeyResolver::class)]
class ConsumerBaseKeyResolverTest extends TestCase
{
    private ConsumerBaseKeyResolver $consumerBaseKey;

    protected function setUp(): void
    {
        $this->consumerBaseKey = new ConsumerBaseKeyResolver();
    }

    #[DataProvider('keyProvider')]
    #[TestDox('reduces a property key to the write boundary\'s uniqueness axis')]
    public function testResolveReducesKeyToItsBaseKey(string $key, string $expected): void
    {
        static::assertSame($expected, $this->consumerBaseKey->resolve($key));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function keyProvider(): iterable
    {
        yield 'undotted key returns itself' => ['product', 'product'];
        yield 'dotted key returns its first segment' => ['product.name', 'product'];
        yield 'multi-dot key returns its first segment' => ['product.name.short', 'product'];
        yield 'leading-dot key returns the empty first segment' => ['.product', ''];
        yield 'empty key returns itself' => ['', ''];
    }
}
