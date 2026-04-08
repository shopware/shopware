<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 */
#[CoversClass(EnrichExportCriteriaEvent::class)]
class EnrichExportCriteriaEventTest extends TestCase
{
    public function testGetContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity());

        static::assertNull($event->getContext());
    }
}
