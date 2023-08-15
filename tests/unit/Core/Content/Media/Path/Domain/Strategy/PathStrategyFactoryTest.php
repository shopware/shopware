<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Path\Domain\Strategy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Path\Implementation\Strategy\BCStrategy;
use Shopware\Core\Content\Media\Path\Implementation\Strategy\FilenamePathStrategy;
use Shopware\Core\Content\Media\Path\Implementation\Strategy\IdPathStrategy;
use Shopware\Core\Content\Media\Path\Implementation\Strategy\PathStrategyFactory;
use Shopware\Core\Content\Media\Path\Implementation\Strategy\PhysicalFilenamePathStrategy;
use Shopware\Core\Content\Media\Path\Implementation\Strategy\PlainPathStrategy;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Path\Implementation\Strategy\PathStrategyFactory
 * @covers \Shopware\Core\Content\Media\Path\Implementation\Strategy\IdPathStrategy
 * @covers \Shopware\Core\Content\Media\Path\Implementation\Strategy\FilenamePathStrategy
 * @covers \Shopware\Core\Content\Media\Path\Implementation\Strategy\PhysicalFilenamePathStrategy
 * @covers \Shopware\Core\Content\Media\Path\Implementation\Strategy\PlainPathStrategy
 */
class PathStrategyFactoryTest extends TestCase
{
    public function testRegistry(): void
    {
        $registry = new PathStrategyFactory([
            new IdPathStrategy(),
            new PhysicalFilenamePathStrategy(),
            new PlainPathStrategy(),
            new FilenamePathStrategy(),
        ], $this->createMock(BCStrategy::class));

        static::assertInstanceOf(FilenamePathStrategy::class, $registry->factory('file_name'));
        static::assertInstanceOf(PhysicalFilenamePathStrategy::class, $registry->factory('physical_file_name'));
        static::assertInstanceOf(PlainPathStrategy::class, $registry->factory('plain'));
        static::assertInstanceOf(IdPathStrategy::class, $registry->factory('id'));

        static::expectException(MediaException::class);
        $registry->factory('invalid');
    }
}
