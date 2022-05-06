<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event\EventData;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;

/**
 * @internal
 */
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
