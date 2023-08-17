<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Core\Path\Strategy;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Path\Strategy\BCStrategy;
use Shopware\Core\Content\Media\Core\Path\Strategy\PathStrategyFactory;
use Shopware\Core\Content\Media\Domain\Path\Struct\MediaLocationStruct;
use Shopware\Core\Content\Media\Domain\Path\Struct\ThumbnailLocationStruct;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\PlainPathnameStrategy;
use Shopware\Core\Content\Media\Pathname\UrlGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Core\Path\Strategy\BCStrategy
 */
class BCStrategyTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testBC(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $bc = new BCStrategy(
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('media_thumbnail.repository'),
            new UrlGenerator(
                new PlainPathnameStrategy(),
                new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']),
            )
        );

        $factory = new PathStrategyFactory([], $bc);

        $strategy = $factory->factory('project-strategy');

        static::assertInstanceOf(BCStrategy::class, $strategy);

        $ids = new IdsCollection();

        $media = [
            'id' => $ids->get('media'),
            'fileName' => 'test',
            'fileExtension' => 'png',
            'thumbnails' => [
                [
                    'id' => $ids->get('thumbnail'),
                    'width' => 100,
                    'height' => 100,
                ],
            ],
        ];

        $this->getContainer()->get('media.repository')->create([$media], Context::createDefaultContext());

        $urls = $strategy->generate([
            'my-key' => new MediaLocationStruct($ids->get('media'), 'png', 'test', new \DateTimeImmutable()),
            'thumbnail' => new ThumbnailLocationStruct($ids->get('thumbnail'), 100, 100, new MediaLocationStruct($ids->get('media'), 'png', 'test', new \DateTimeImmutable())),
        ]);

        static::assertArrayHasKey('my-key', $urls);
        static::assertEquals('media/test.png', $urls['my-key']);

        static::assertArrayHasKey('thumbnail', $urls);
        static::assertEquals('thumbnail/test_100x100.png', $urls['thumbnail']);
    }
}
