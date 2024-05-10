<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;

/**
 * @internal
 */
#[CoversClass(ObjectType::class)]
class ObjectTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $expected = [
            'type' => 'object',
            'data' => [
                'myBool' => [
                    'type' => 'bool',
                ],
                'myString' => [
                    'type' => 'string',
                ],
            ],
        ];

        static::assertEquals(
            $expected,
            (new ObjectType())
                ->add('myBool', new ScalarValueType(ScalarValueType::TYPE_BOOL))
                ->add('myString', new ScalarValueType(ScalarValueType::TYPE_STRING))
                ->toArray()
        );
    }
}
