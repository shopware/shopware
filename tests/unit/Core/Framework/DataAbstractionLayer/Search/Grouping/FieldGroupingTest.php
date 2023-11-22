<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;

/**
 * @internal
 */
#[CoversClass(FieldGrouping::class)]
class FieldGroupingTest extends TestCase
{
    public function testEncode(): void
    {
        $fieldGrouping = new FieldGrouping('test');

        static::assertEquals(
            [
                'field' => 'test',
                'extensions' => [],
            ],
            $fieldGrouping->jsonSerialize()
        );
    }

    public function testClone(): void
    {
        $fieldGrouping = new FieldGrouping('test');

        $clone = clone $fieldGrouping;

        static::assertEquals($fieldGrouping, $clone);
        static::assertEquals($fieldGrouping->getField(), $clone->getField());
        static::assertEquals($fieldGrouping->jsonSerialize(), $clone->jsonSerialize());
    }
}
