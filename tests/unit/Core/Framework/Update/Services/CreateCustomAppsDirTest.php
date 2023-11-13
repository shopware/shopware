<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Services\CreateCustomAppsDir;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Update\Services\CreateCustomAppsDir
 */
class CreateCustomAppsDirTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $subscribedEvents = CreateCustomAppsDir::getSubscribedEvents();
        static::assertArrayHasKey('Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent', $subscribedEvents);
        static::assertSame('onUpdate', $subscribedEvents['Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent']);
    }

    public function testItDoesCreateDirIfItDoesNotExist(): void
    {
        try {
            $service = new CreateCustomAppsDir(__DIR__ . '/test');
            $service->onUpdate();

            static::assertDirectoryExists(__DIR__ . '/test');
        } finally {
            rmdir(__DIR__ . '/test');
        }
    }

    public function testItDoesNotTouchExistingDirectory(): void
    {
        try {
            mkdir(__DIR__ . '/test');
            touch(__DIR__ . '/test/file');

            $service = new CreateCustomAppsDir(__DIR__ . '/test');
            $service->onUpdate();

            static::assertFileExists(__DIR__ . '/test/file');
        } finally {
            unlink(__DIR__ . '/test/file');
            rmdir(__DIR__ . '/test');
        }
    }
}
