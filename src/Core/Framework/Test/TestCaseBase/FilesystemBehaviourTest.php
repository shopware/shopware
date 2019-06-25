<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\TestCase;

class FilesystemBehaviourTest extends TestCase
{
    use FilesystemBehaviour;
    use KernelTestBehaviour;

    public function testWrittenFilesGetDeleted(): void
    {
        $this->getPublicFilesystem()
            ->put('testFile', 'testContent');

        $this->getPublicFilesystem()
            ->put('public/testFile', 'testContent');

        static::assertNotEmpty($this->getPublicFilesystem()->listContents());
    }

    /**
     * @depends testWrittenFilesGetDeleted
     */
    public function testFileSystemIsEmptyOnNextTest(): void
    {
        static::assertEmpty($this->getPublicFilesystem()->listContents());
    }
}
