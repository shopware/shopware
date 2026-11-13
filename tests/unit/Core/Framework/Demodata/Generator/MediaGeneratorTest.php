<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\Generator\MediaGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaGenerator::class)]
class MediaGeneratorTest extends TestCase
{
    public function testGenerateWithoutDefaultFoldersCreatesNoMediaFolder(): void
    {
        $folderRepository = StaticEntityRepository::of(MediaFolderCollection::class, []);

        $generator = $this->createGenerator([[], []], $folderRepository);

        $generator->generate(0, $this->createContext());

        static::assertSame([], $folderRepository->upserts);
    }

    public function testGenerateCreatesMediaFolderForDefaultFolderWithoutOne(): void
    {
        $defaultFolderId = Uuid::randomHex();

        $folderRepository = StaticEntityRepository::of(MediaFolderCollection::class, [
            static function (Criteria $criteria) use ($defaultFolderId): array {
                static::assertEquals([new EqualsFilter('defaultFolderId', $defaultFolderId)], $criteria->getFilters());

                return [];
            },
        ]);

        $generator = $this->createGenerator([[$defaultFolderId], []], $folderRepository);

        $generator->generate(0, $this->createContext());

        static::assertCount(1, $folderRepository->upserts);
        $upsert = $folderRepository->upserts[0][0];
        static::assertIsArray($upsert);
        static::assertSame($defaultFolderId, $upsert['defaultFolderId']);
        static::assertSame('Product Media', $upsert['name']);
    }

    public function testGenerateReusesExistingMediaFolderOfDefaultFolder(): void
    {
        $folderRepository = StaticEntityRepository::of(MediaFolderCollection::class, [[Uuid::randomHex()]]);

        $generator = $this->createGenerator([[Uuid::randomHex()], []], $folderRepository);

        $generator->generate(0, $this->createContext());

        static::assertSame([], $folderRepository->upserts);
    }

    /**
     * @param list<list<string>> $defaultFolderIds
     * @param StaticEntityRepository<MediaFolderCollection> $folderRepository
     */
    private function createGenerator(array $defaultFolderIds, StaticEntityRepository $folderRepository): MediaGenerator
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $defaultFolderRepository = StaticEntityRepository::of(MediaDefaultFolderCollection::class, $defaultFolderIds);

        return new MediaGenerator(
            static::createStub(EntityWriterInterface::class),
            static::createStub(FileSaver::class),
            static::createStub(FileNameProvider::class),
            $defaultFolderRepository,
            $folderRepository,
            new MediaDefinition(),
            $connection,
        );
    }

    private function createContext(): DemodataContext
    {
        $context = static::createStub(DemodataContext::class);
        $context->method('getFaker')->willReturn(Factory::create());
        $context->method('getConsole')->willReturn(static::createStub(SymfonyStyle::class));
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }
}
