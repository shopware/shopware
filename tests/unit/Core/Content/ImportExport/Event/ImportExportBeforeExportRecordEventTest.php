<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 */
#[CoversClass(ImportExportBeforeExportRecordEvent::class)]
class ImportExportBeforeExportRecordEventTest extends TestCase
{
    public function testGetContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new ImportExportBeforeExportRecordEvent(
            new Config([], [], []),
            ['key' => 'value'],
            ['key' => 'original']
        );

        static::assertNull($event->getContext());
    }
}
