<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;

/**
 * @internal
 */
#[CoversClass(ScalarValueType::class)]
class ScalarValueTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $expected = [
            'type' => 'float',
        ];

        static::assertEquals($expected, (new ScalarValueType(ScalarValueType::TYPE_FLOAT))->toArray());
    }

    public function testThrowExceptionOnInvalidType(): void
    {
        static::expectException(\InvalidArgumentException::class);

        new ScalarValueType('test');
    }
}
