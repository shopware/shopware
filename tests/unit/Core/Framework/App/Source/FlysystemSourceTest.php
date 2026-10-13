<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Source;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppDownloader;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Event\PostAppDeletedEvent;
use Shopware\Core\Framework\App\Source\FlysystemSource;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Context;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(FlysystemSource::class)]
class FlysystemSourceTest extends TestCase
{
    public function testDeletesOnlyAppsOwnedByConfiguredSourceType(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())->method('deleteDirectory')->with('private-apps/ExampleApp');

        $source = new FlysystemSource(
            $storage,
            new TemporaryDirectoryFactory(),
            new Filesystem(),
            static::createStub(AppDownloader::class),
            static::createStub(AppExtractor::class),
            new NullLogger(),
            'private-app',
            'private-apps',
        );

        $source->onAppDeleted(new PostAppDeletedEvent('ExampleApp', 'private-app', Context::createDefaultContext()));
        $source->onAppDeleted(new PostAppDeletedEvent('StoreApp', 'store', Context::createDefaultContext()));
    }
}
