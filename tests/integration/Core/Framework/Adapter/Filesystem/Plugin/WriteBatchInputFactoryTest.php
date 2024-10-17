<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Filesystem\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\WriteBatchInputFactory;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(WriteBatchInputFactory::class)]
class WriteBatchInputFactoryTest extends TestCase
{
    public function testWriteBatchInputFactoryUsingDirectory(): void
    {
        $fs = new Filesystem();
        $fs->mkdir(__DIR__ . '/test');
        $fs->touch([
            __DIR__ . '/test/file1',
            __DIR__ . '/test/file2',
            __DIR__ . '/test/file3',
        ]);

        $writeBatch = (new WriteBatchInputFactory())->fromDirectory(__DIR__ . '/test', __DIR__ . '/target');
        sort($writeBatch);

        static::assertCount(3, $writeBatch);

        static::assertSame(__DIR__ . '/test/file1', $writeBatch[0]->getSourceFile());
        static::assertSame([__DIR__ . '/target/test/file1'], $writeBatch[0]->getTargetFiles());

        static::assertSame(__DIR__ . '/test/file2', $writeBatch[1]->getSourceFile());
        static::assertSame([__DIR__ . '/target/test/file2'], $writeBatch[1]->getTargetFiles());

        static::assertSame(__DIR__ . '/test/file3', $writeBatch[2]->getSourceFile());
        static::assertSame([__DIR__ . '/target/test/file3'], $writeBatch[2]->getTargetFiles());

        $fs->remove(__DIR__ . '/test');
    }
}
