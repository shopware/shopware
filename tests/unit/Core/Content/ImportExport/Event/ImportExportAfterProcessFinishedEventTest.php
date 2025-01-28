<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportAfterProcessFinishedEventTest extends TestCase
{
    public function testReturnsCorrectProperties(): void
    {
        $context = Context::createDefaultContext();
        $logEntity = new ImportExportLogEntity();
        $progress = new Progress('log-id', Progress::STATE_SUCCEEDED);

        $event = new ImportExportAfterProcessFinishedEvent(
            $context,
            $logEntity,
            $progress,
        );

        static::assertSame($context, $event->getContext());
        static::assertSame($logEntity, $event->getLogEntity());
        static::assertSame($progress, $event->getProgress());
    }
}
