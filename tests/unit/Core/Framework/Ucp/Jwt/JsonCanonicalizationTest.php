<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Jwt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Jwt\JsonCanonicalization;

/**
 * @internal
 */
#[CoversClass(JsonCanonicalization::class)]
class JsonCanonicalizationTest extends TestCase
{
    public function testSortsObjectKeysRecursivelyWithoutChangingArrayOrder(): void
    {
        $canonical = JsonCanonicalization::encode([
            'b' => 2,
            'a' => [
                'd' => 4,
                'c' => [3, 2, 1],
            ],
        ]);

        static::assertSame('{"a":{"c":[3,2,1],"d":4},"b":2}', $canonical);
    }

    public function testEscapesControlCharactersDeterministically(): void
    {
        static::assertSame('{"x":"line\\nfeed"}', JsonCanonicalization::encode(['x' => "line\nfeed"]));
    }
}
