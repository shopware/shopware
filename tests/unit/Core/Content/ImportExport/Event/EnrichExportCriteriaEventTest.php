<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(EnrichExportCriteriaEvent::class)]
class EnrichExportCriteriaEventTest extends TestCase
{
    public function testGetContextReturnsPassedContext(): void
    {
        $context = Context::createDefaultContext();
        $event = new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity(), $context);

        static::assertSame($context, $event->getContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsContextWhenFeatureInactiveAndContextProvided(): void
    {
        $context = Context::createDefaultContext();
        $event = new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity(), $context);

        static::assertSame($context, $event->getNullableContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        $event = new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity());

        static::assertNull($event->getNullableContext());
    }

    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $context to ' . EnrichExportCriteriaEvent::class . ' is deprecated and will be required in v6.8.0.'
        ));
        new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetContextThrowsWithoutContext(): void
    {
        $event = new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity());

        $this->expectExceptionObject(ImportExportException::invalidEventData('No context provided. Pass $context to the constructor of ' . EnrichExportCriteriaEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextThrowsWhenFeatureActive(): void
    {
        $event = new EnrichExportCriteriaEvent(new Criteria(), new ImportExportLogEntity(), Context::createDefaultContext());

        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: getNullableContext() is deprecated, use getContext() instead.'));
        $event->getNullableContext();
    }
}
