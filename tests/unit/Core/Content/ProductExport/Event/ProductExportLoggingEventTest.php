<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(ProductExportLoggingEvent::class)]
class ProductExportLoggingEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new ProductExportLoggingEvent(
            Context::createDefaultContext(),
            'custom-name',
            null
        );

        $storer = new ScalarValuesStorer();
        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('name', $flow->data());
        static::assertEquals('custom-name', $flow->data()['name']);
    }
}
