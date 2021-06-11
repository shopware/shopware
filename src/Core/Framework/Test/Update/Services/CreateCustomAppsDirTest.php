<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Services\CreateCustomAppsDir;

class CreateCustomAppsDirTest extends TestCase
{
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
