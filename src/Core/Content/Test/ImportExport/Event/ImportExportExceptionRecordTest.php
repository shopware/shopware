<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;

class ImportExportExceptionRecordTest extends TestCase
{
    /**
     * @var ImportExportExceptionImportRecordEvent
     */
    private $exceptionRecord;

    public function setUp(): void
    {
        $exception = $this->createMock(\Throwable::class);
        $context = $this->createMock(Context::class);
        $config = $this->createMock(Config::class);

        $this->exceptionRecord = new ImportExportExceptionImportRecordEvent(
            $exception,
            [],
            [],
            $config,
            $context
        );
    }

    public function testHasException(): void
    {
        static::assertTrue($this->exceptionRecord->hasException());
        static::assertInstanceOf(\Throwable::class, $this->exceptionRecord->getException());
    }

    public function testRemoveException(): void
    {
        $this->exceptionRecord->removeException();
        static::assertFalse($this->exceptionRecord->hasException());
        static::assertNull($this->exceptionRecord->getException());
    }

    public function testReplaceException(): void
    {
        $this->exceptionRecord->removeException();
        $newException = $this->createMock(\Throwable::class);
        $this->exceptionRecord->setException($newException);
        static::assertTrue($this->exceptionRecord->hasException());
    }
}
