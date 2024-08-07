<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Core\Strategy;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Strategy\BCStrategy;
use Shopware\Core\Content\Media\Core\Strategy\FilenamePathStrategy;
use Shopware\Core\Content\Media\Core\Strategy\IdPathStrategy;
use Shopware\Core\Content\Media\Core\Strategy\PathStrategyFactory;
use Shopware\Core\Content\Media\Core\Strategy\PhysicalFilenamePathStrategy;
use Shopware\Core\Content\Media\Core\Strategy\PlainPathStrategy;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Core\Strategy\PathStrategyFactory
 * @covers \Shopware\Core\Content\Media\Core\Strategy\IdPathStrategy
 * @covers \Shopware\Core\Content\Media\Core\Strategy\FilenamePathStrategy
 * @covers \Shopware\Core\Content\Media\Core\Strategy\PhysicalFilenamePathStrategy
 * @covers \Shopware\Core\Content\Media\Core\Strategy\PlainPathStrategy
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

        static::assertInstanceOf(FilenamePathStrategy::class, $registry->factory('filename'));
        static::assertInstanceOf(PhysicalFilenamePathStrategy::class, $registry->factory('physical_filename'));
        static::assertInstanceOf(PlainPathStrategy::class, $registry->factory('plain'));
        static::assertInstanceOf(IdPathStrategy::class, $registry->factory('id'));

        if (Feature::isActive('v6.6.0.0')) {
            static::expectException(MediaException::class);
        }

        $factory = $registry->factory('invalid');

        if (!Feature::isActive('v6.6.0.0')) {
            static::assertInstanceOf(BCStrategy::class, $factory);
        }
    }
}
