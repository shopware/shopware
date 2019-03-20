<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event\EventData;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;

class ScalarValueTypeTest extends TestCase
{
    public function testToArray()
    {
        $expected = [
            'type' => 'float',
        ];

        static::assertEquals($expected, (new ScalarValueType(ScalarValueType::TYPE_FLOAT))->toArray());
    }

    public function testThrowExceptionOnInvalidType()
    {
        static::expectException(\InvalidArgumentException::class);

        new ScalarValueType('test');
    }
}
