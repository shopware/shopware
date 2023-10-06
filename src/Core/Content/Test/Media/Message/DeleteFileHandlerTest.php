<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Message;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Message\DeleteFileHandler;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class DeleteFileHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var DeleteFileHandler
     */
    private $handler;

    protected function setUp(): void
    {
        $this->handler = $this->getContainer()->get(DeleteFileHandler::class);
    }

    public function testItHandlesDeletes(): void
    {
        $filesystem = $this->getPublicFilesystem();

        $file1 = 'test/file1.txt';
        $file2 = 'test/file2.txt';

        $filesystem->write($file1, 'file 1 content');
        $filesystem->write($file2, 'file 2 content');

        $deleteMsg = new DeleteFileMessage();
        $deleteMsg->setFiles([$file1, $file2]);

        $this->handler->__invoke($deleteMsg);

        static::assertFalse($filesystem->has($file1));
        static::assertFalse($filesystem->has($file2));
    }

    public function testItDealsWithMissingFiles(): void
    {
        $filesystem = $this->getPublicFilesystem();

        $file1 = 'test/file1.txt';
        $file2 = 'test/file2.txt';
        $file3 = 'test/file3.txt';

        $filesystem->write($file1, 'file 1 content');
        $filesystem->write($file3, 'file 3 content');

        $deleteMsg = new DeleteFileMessage();
        $deleteMsg->setFiles([$file1, $file2, $file3]);

        $this->handler->__invoke($deleteMsg);

        static::assertFalse($filesystem->has($file1));
        static::assertFalse($filesystem->has($file2));
        static::assertFalse($filesystem->has($file3));
    }
}
