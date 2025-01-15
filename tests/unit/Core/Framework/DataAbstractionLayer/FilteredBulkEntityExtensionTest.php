<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FilteredBulkEntityExtension;

/**
 * @internal
 */
#[CoversClass(FilteredBulkEntityExtension::class)]
class FilteredBulkEntityExtensionTest extends TestCase
{
    public function testFilteredGetsAdded(): void
    {
        $bulk = new class extends BulkEntityExtension {
            public function collect(): \Generator
            {
                yield 'foo' => [
                    new StringField('bar', 'bar'),
                ];

                yield 'foo2' => [
                    new StringField('bar', 'bar'),
                ];
            }
        };

        $extension = new FilteredBulkEntityExtension('foo', $bulk);
        $fields = new FieldCollection();

        $extension->extendFields($fields);

        static::assertCount(1, $fields);
        $first = $fields->first();
        static::assertInstanceOf(StringField::class, $first);
        static::assertSame('bar', $first->getStorageName());
    }
}
