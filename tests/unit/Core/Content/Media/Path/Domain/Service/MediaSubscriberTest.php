<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Path\Domain\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Path\Domain\Service\MediaPathSubscriber;
use Shopware\Core\Content\Media\Path\Infrastructure\Service\MediaUrlGenerator;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Test\IdsCollection;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Path\Domain\Service\MediaPathSubscriber
 */
class MediaSubscriberTest extends TestCase
{
    /**
     * @dataProvider loadedProvider
     *
     * @param array<string, string> $expected
     */
    public function testLoad(IdsCollection $ids, PartialEntity $entity, array $expected): void
    {
        $mock = $this->createMock(UrlGeneratorInterface::class);
        $mock->expects(static::never())
            ->method('getAbsoluteMediaUrl');

        $mock->expects(static::never())
            ->method('getRelativeMediaUrl');

        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $subscriber = new MediaPathSubscriber(new MediaUrlGenerator($filesystem), $mock);

        $subscriber->loaded([$entity]);

        $actual = [$entity->get('id') => $entity->get('url')];

        if ($entity->get('thumbnails') instanceof EntityCollection) {
            foreach ($entity->get('thumbnails') as $thumbnail) {
                $actual[$thumbnail->get('id')] = $thumbnail->get('url');
            }
        }

        foreach ($expected as $id => $value) {
            static::assertArrayHasKey($id, $actual);
            static::assertEquals($value, $actual[$id]);
        }
    }

    public static function loadedProvider(): \Generator
    {
        $ids = new IdsCollection();
        yield 'Test without updated at' => [
            $ids,
            (new PartialEntity())->assign(['id' => $ids->get('media'), 'path' => '/foo/bar.png']),
            [$ids->get('media') => 'http://localhost:8000/foo/bar.png'],
        ];

        yield 'Test with updated at' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => '/foo/bar.png',
                'updatedAt' => new \DateTimeImmutable('2000-01-01'),
            ]),
            [$ids->get('media') => 'http://localhost:8000/foo/bar.png?946684800'],
        ];

        yield 'Test with thumbnails' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => '/foo/bar.png',
                'thumbnails' => [
                    (new PartialEntity())->assign([
                        'id' => $ids->get('thumbnail'),
                        'path' => '/foo/bar.png',
                    ]),
                ],
            ]),
            [
                $ids->get('media') => 'http://localhost:8000/foo/bar.png',
                $ids->get('thumbnail') => 'http://localhost:8000/foo/bar.png',
            ],
        ];

        yield 'Test with thumbnails and updated at' => [
            $ids,
            (new PartialEntity())->assign([
                'id' => $ids->get('media'),
                'path' => '/foo/bar.png',
                'updatedAt' => new \DateTimeImmutable('2000-01-01'),
                'thumbnails' => [
                    (new PartialEntity())->assign([
                        'id' => $ids->get('thumbnail'),
                        'path' => '/foo/bar.png',
                    ]),
                ],
            ]),
            [
                $ids->get('media') => 'http://localhost:8000/foo/bar.png?946684800',
                $ids->get('thumbnail') => 'http://localhost:8000/foo/bar.png?946684800',
            ],
        ];
    }
}
