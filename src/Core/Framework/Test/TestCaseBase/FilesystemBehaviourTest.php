<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class FilesystemBehaviourTest extends TestCase
{
    use FilesystemBehaviour;
    use KernelTestBehaviour;

    public function testWrittenFilesGetDeleted(): void
    {
        $this->getPublicFilesystem()
            ->write('testFile', 'testContent');

        $this->getPublicFilesystem()
            ->write('public/testFile', 'testContent');

        static::assertNotEmpty($this->getPublicFilesystem()->listContents('', true)->toArray());
    }

    /**
     * @depends testWrittenFilesGetDeleted
     */
    public function testFileSystemIsEmptyOnNextTest(): void
    {
        static::assertEmpty($this->getPublicFilesystem()->listContents('', true)->toArray());
    }
}
