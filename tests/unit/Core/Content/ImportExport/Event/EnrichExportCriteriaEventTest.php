<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(EnrichExportCriteriaEvent::class)]
class EnrichExportCriteriaEventTest extends TestCase
{
    public function testGetters(): void
    {
        $criteria = new Criteria();
        $logEntity = new ImportExportLogEntity();
        $context = Context::createDefaultContext();

        $event = new EnrichExportCriteriaEvent($criteria, $logEntity, $context);

        static::assertSame($criteria, $event->getCriteria());
        static::assertSame($logEntity, $event->getLogEntity());
        static::assertSame($context, $event->getContext());
    }

    public function testSetCriteria(): void
    {
        $event = new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity(), Context::createDefaultContext());

        $newCriteria = new Criteria();
        $event->setCriteria($newCriteria);

        static::assertSame($newCriteria, $event->getCriteria());
    }

    public function testSetLogEntity(): void
    {
        $event = new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity(), Context::createDefaultContext());

        $newLogEntity = new ImportExportLogEntity();
        $event->setLogEntity($newLogEntity);

        static::assertSame($newLogEntity, $event->getLogEntity());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity());

        $this->expectException(ImportExportException::class);
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity());

        static::assertNull(@$event->getNullableContext());
    }
}
