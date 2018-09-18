<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\TestCase;

class FilesystemBehaviourTest extends TestCase
{
    use FilesystemBehaviour;

    public function test_writtenFilesGetDeleted(): void
    {
        $this->getPublicFilesystem()
            ->put('testFile', 'testContent');
        $this->getPublicFilesystem()
            ->put('public/testFile', 'testContent');
        static::assertNotEmpty($this->getPublicFilesystem()->listContents());
    }

    /**
     * @depends test_writtenFilesGetDeleted
     */
    public function test_fileSystemIsEmptyOnNextTest(): void
    {
        static::assertEmpty($this->getPublicFilesystem()->listContents());
    }
}
