<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;

/**
 * @internal
 */
#[CoversClass(EnrichExportCriteriaEvent::class)]
class EnrichExportCriteriaEventTest extends TestCase
{
    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $this->expectException(FeatureException::class);
        new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity());

        $this->expectExceptionObject(ImportExportException::invalidEventData('No context provided. Pass $context to the constructor of ' . EnrichExportCriteriaEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity());

        static::assertNull($event->getNullableContext());
    }
}
