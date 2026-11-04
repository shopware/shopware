<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Commands;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Commands\GenerateMediaTypesCommand;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GenerateMediaTypesCommand::class)]
class GenerateMediaTypesCommandTest extends TestCase
{
    #[TestDox('The detected media type is persisted for every media with a file')]
    public function testDetectsAndPersistsMediaTypes(): void
    {
        $media = $this->createMediaEntity(withFile: true);
        $repository = $this->createMediaRepository($media);

        $type = new ImageType();
        $typeDetector = $this->createMock(TypeDetector::class);
        $typeDetector
            ->expects($this->once())
            ->method('detect')
            ->willReturn($type);

        $commandTester = new CommandTester(new GenerateMediaTypesCommand($typeDetector, $repository));

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));

        static::assertCount(1, $repository->upserts);
        static::assertSame(
            [['id' => $media->getId(), 'mediaTypeRaw' => serialize($type)]],
            $repository->upserts[0]
        );
    }

    #[TestDox('Media without a file is skipped')]
    public function testSkipsMediaWithoutFile(): void
    {
        $repository = $this->createMediaRepository($this->createMediaEntity(withFile: false));

        $typeDetector = $this->createMock(TypeDetector::class);
        $typeDetector->expects($this->never())->method('detect');

        $commandTester = new CommandTester(new GenerateMediaTypesCommand($typeDetector, $repository));

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));
        static::assertSame([], $repository->upserts);
    }

    #[TestDox('A non-numeric batch size is rejected')]
    public function testThrowsOnInvalidBatchSize(): void
    {
        $repository = $this->createMediaRepository($this->createMediaEntity(withFile: true));
        $typeDetector = $this->createMock(TypeDetector::class);
        $typeDetector->expects($this->never())->method('detect');

        $commandTester = new CommandTester(new GenerateMediaTypesCommand($typeDetector, $repository));

        $this->expectExceptionObject(MediaException::invalidBatchSize());

        $commandTester->execute(['--batch-size' => 'not-a-number']);
    }

    private function createMediaEntity(bool $withFile): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setUrl('http://shop.example/media/test.png');
        if ($withFile) {
            $media->setFileName('test');
            $media->setMimeType('image/png');
            $media->setFileExtension('png');
            $media->setFileSize(1024);
        }

        return $media;
    }

    /**
     * @return StaticEntityRepository<MediaCollection>
     */
    private function createMediaRepository(MediaEntity $media): StaticEntityRepository
    {
        // first search returns the total count, second search the first (and only) batch
        $repository = new StaticEntityRepository([
            new MediaCollection([$media]),
            new MediaCollection([$media]),
        ]);

        return $repository;
    }
}
