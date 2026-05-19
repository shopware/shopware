<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\Script\ScriptReferenceDataCollector;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[CoversClass(ScriptReferenceDataCollector::class)]
class ScriptReferenceDataCollectorTest extends TestCase
{
    protected function tearDown(): void
    {
        ScriptReferenceDataCollector::reset();
        parent::tearDown();
    }

    public function testSetAndGetShopwareClasses(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([\stdClass::class, \Countable::class]);

        static::assertSame([\stdClass::class, \Countable::class], ScriptReferenceDataCollector::getShopwareClasses());
    }

    public function testSetAndGetFiles(): void
    {
        $file = static::createStub(SplFileInfo::class);
        ScriptReferenceDataCollector::setFiles(['foo.php' => $file]);

        static::assertSame(['foo.php' => $file], ScriptReferenceDataCollector::getFiles());
    }

    public function testResetClearsClasses(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([\stdClass::class]);
        ScriptReferenceDataCollector::reset();

        // After reset, setShopwareClasses with new data must be accepted
        ScriptReferenceDataCollector::setShopwareClasses([\Countable::class]);
        static::assertSame([\Countable::class], ScriptReferenceDataCollector::getShopwareClasses());
    }

    public function testResetClearsFiles(): void
    {
        $file = static::createStub(SplFileInfo::class);
        ScriptReferenceDataCollector::setFiles(['foo.php' => $file]);
        ScriptReferenceDataCollector::reset();

        // After reset, setFiles with new data must be accepted
        $newFile = static::createStub(SplFileInfo::class);
        ScriptReferenceDataCollector::setFiles(['bar.php' => $newFile]);
        static::assertSame(['bar.php' => $newFile], ScriptReferenceDataCollector::getFiles());
    }

    public function testGetShopwareClassesLoadsRealClassesWhenNotSet(): void
    {
        // Point scan at a small fixture directory to avoid full codebase scan
        ScriptReferenceDataCollector::setScanPath(__DIR__ . '/_fixtures');

        $classes = ScriptReferenceDataCollector::getShopwareClasses();

        static::assertIsArray($classes);
        static::assertNotEmpty($classes);
        foreach ($classes as $class) {
            static::assertIsString($class);
        }
    }

    public function testGetShopwareClassesIsCachedAfterFirstCall(): void
    {
        ScriptReferenceDataCollector::setScanPath(__DIR__ . '/_fixtures');

        $first = ScriptReferenceDataCollector::getShopwareClasses();
        $second = ScriptReferenceDataCollector::getShopwareClasses();

        static::assertSame($first, $second);
    }

    public function testGetFilesLoadsRealFilesWhenNotSet(): void
    {
        // Point finder at a small fixture directory to avoid full codebase scan
        ScriptReferenceDataCollector::setFinderPaths([__DIR__ . '/_fixtures']);

        $files = ScriptReferenceDataCollector::getFiles();

        static::assertIsArray($files);
        static::assertNotEmpty($files);
    }

    public function testGetFilesIsCachedAfterFirstCall(): void
    {
        ScriptReferenceDataCollector::setFinderPaths([__DIR__ . '/_fixtures']);

        $first = ScriptReferenceDataCollector::getFiles();
        $second = ScriptReferenceDataCollector::getFiles();

        static::assertSame($first, $second);
    }

    public function testResetAlsoClearsScanPathAndFinderPaths(): void
    {
        ScriptReferenceDataCollector::setScanPath(__DIR__ . '/_fixtures');
        ScriptReferenceDataCollector::setFinderPaths([__DIR__ . '/_fixtures']);
        ScriptReferenceDataCollector::reset();

        // After reset, setScanPath with a new path must work
        ScriptReferenceDataCollector::setScanPath(__DIR__ . '/_fixtures');
        $classes = ScriptReferenceDataCollector::getShopwareClasses();
        static::assertIsArray($classes);
    }
}
