<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(JsonField::class)]
class JsonFieldTest extends TestCase
{
    public function testInstantiateWithPropertyMapping(): void
    {
        $productMapping = new JsonField('product', 'product', [
            new IntField('maxSuggestCount', 'maxSuggestCount'),
        ]);
        $field = new JsonField('hit_count', 'hitCount', [$productMapping], ['product' => ['maxSuggestCount' => 10]]);

        static::assertSame('hit_count', $field->getStorageName());
        static::assertSame('hitCount', $field->getPropertyName());
        static::assertSame([$productMapping], $field->getPropertyMapping());
        static::assertSame(['product' => ['maxSuggestCount' => 10]], $field->getDefault());
    }

    public function testAddPropertyMappingAppendsNestedFields(): void
    {
        $productMapping = new JsonField('product', 'product', [
            new IntField('maxSuggestCount', 'maxSuggestCount'),
        ]);
        $field = new JsonField('hit_count', 'hitCount', [$productMapping]);

        $extendedMapping = new JsonField('landing_page', 'landing_page', [
            new IntField('maxSuggestCount', 'maxSuggestCount'),
            new IntField('maxSearchCount', 'maxSearchCount'),
        ]);

        $returned = $field->addPropertyMapping($extendedMapping);

        static::assertSame($field, $returned);
        static::assertSame([$productMapping, $extendedMapping], $field->getPropertyMapping());
    }
}
