<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Path\Infrastructure\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaLocationBuilder;
use Shopware\Core\Content\Media\Path\Domain\Service\MediaPathUpdater;
use Shopware\Core\Content\Media\Path\Domain\Strategy\PlainPathStrategy;
use Shopware\Core\Content\Media\Path\Infrastructure\Command\UpdatePath;
use Shopware\Core\Content\Media\Path\Infrastructure\Service\MediaPathStorage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Path\Infrastructure\Command\UpdatePath
 */
class UpdatePathTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider commandProvider
     *
     * @param array<mixed> $media
     * @param array<mixed> $thumbnail
     * @param array<string, string> $expected
     */
    public function testCommand(array $media, array $thumbnail, ArrayInput $input, array $expected): void
    {
        $ids = new IdsCollection();

        $queue = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));

        $media['id'] = $ids->getBytes('media');
        $queue->addInsert('media', $media);

        $thumbnail['id'] = $ids->getBytes('media_thumbnail');
        $thumbnail['media_id'] = $ids->getBytes('media');
        $queue->addInsert('media_thumbnail', $thumbnail);

        $queue->execute();

        $command = new UpdatePath(
            new MediaPathUpdater(
                new PlainPathStrategy(),
                $this->getContainer()->get(AbstractMediaLocationBuilder::class),
                $this->getContainer()->get(MediaPathStorage::class)
            ),
            $this->getContainer()->get(IteratorFactory::class)
        );

        $command->run($input, new NullOutput());

        $paths = $this->getContainer()
            ->get(Connection::class)
            ->fetchAllKeyValue(
                'SELECT LOWER(HEX(id)), path FROM media WHERE id IN (:ids)',
                ['ids' => $ids->getByteList(['media'])],
                ['ids' => ArrayParameterType::STRING]
            );

        static::assertArrayHasKey($ids->get('media'), $paths);
        static::assertSame($expected['media'], $paths[$ids->get('media')]);

        $paths = $this->getContainer()
            ->get(Connection::class)
            ->fetchAllKeyValue(
                'SELECT LOWER(HEX(id)), path FROM media_thumbnail WHERE id IN (:ids)',
                ['ids' => $ids->getByteList(['media_thumbnail'])],
                ['ids' => ArrayParameterType::STRING]
            );

        static::assertArrayHasKey($ids->get('media_thumbnail'), $paths);
        static::assertSame($expected['thumbnail'], $paths[$ids->get('media_thumbnail')]);
    }

    public static function commandProvider(): \Generator
    {
        yield 'Test generate' => [
            [
                'file_name' => 'test',
                'file_extension' => 'png',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'width' => 100,
                'height' => 100,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            new ArrayInput([]),
            [
                'media' => 'media/test.png',
                'thumbnail' => 'thumbnail/test_100x100.png',
            ],
        ];

        yield 'Test skip generation when path is already set' => [
            [
                'file_name' => 'test',
                'file_extension' => 'png',
                'path' => 'foo/test.png',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'width' => 100,
                'height' => 100,
                'path' => 'foo/test_100x100.png',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            new ArrayInput([]),
            [
                'media' => 'foo/test.png',
                'thumbnail' => 'foo/test_100x100.png',
            ],
        ];

        yield 'Test force parameter overwrites the path' => [
            [
                'file_name' => 'test',
                'file_extension' => 'png',
                'path' => 'foo/test.png',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'width' => 100,
                'height' => 100,
                'path' => 'foo/test_100x100.png',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            new ArrayInput(['--force' => true]),
            [
                'media' => 'media/test.png',
                'thumbnail' => 'thumbnail/test_100x100.png',
            ],
        ];
    }
}
