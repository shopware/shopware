<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaHydrator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(MediaHydrator::class)]
class MediaHydratorTest extends TestCase
{
    private MediaHydrator $hydrator;

    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $container = new ContainerBuilder();
        $this->hydrator = new MediaHydrator($container);

        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [
                MediaDefinition::class,
                UserDefinition::class,
                MediaFolderDefinition::class,
                MediaTranslationDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $container->set(MediaHydrator::class, $this->hydrator);
    }

    public function testHydration(): void
    {
        $definition = $this->definitionInstanceRegistry->get(MediaDefinition::class);

        $id = Uuid::randomBytes();
        $userId = Uuid::randomBytes();
        $mediaFolderId = Uuid::randomBytes();
        $date = new \DateTime();

        $rows = [
            [
                'test.id' => $id,
                'test.userId' => $userId,
                'test.mediaFolderId' => $mediaFolderId,
                'test.mimeType' => 'image/jpeg',
                'test.fileExtension' => 'jpg',
                'test.fileSize' => 100,
                'test.fileName' => 'foo.jpg',
                'test.uploadedAt' => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'test.createdAt' => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'test.updatedAt' => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'test.path' => 'media/foo.jpg',
                'test.private' => false,
                'test.metaDataRaw' => json_encode(['foo' => 'bar']),
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', Context::createDefaultContext());
        static::assertCount(1, $structs);

        /** @var MediaEntity|null $first */
        $first = $structs->first();

        static::assertInstanceOf(MediaEntity::class, $first);

        static::assertSame(Uuid::fromBytesToHex($id), $first->getId());
        static::assertSame(Uuid::fromBytesToHex($userId), $first->getUserId());
        static::assertSame(Uuid::fromBytesToHex($mediaFolderId), $first->getMediaFolderId());
        static::assertSame('image/jpeg', $first->getMimeType());
        static::assertSame('jpg', $first->getFileExtension());
        static::assertSame(100, $first->getFileSize());
        static::assertSame('foo.jpg', $first->getFileName());
        static::assertInstanceOf(\DateTimeInterface::class, $first->getUploadedAt());
        static::assertEquals($date->format(Defaults::STORAGE_DATE_TIME_FORMAT), $first->getUploadedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        static::assertInstanceOf(\DateTimeInterface::class, $first->getCreatedAt());
        static::assertEquals($date->format(Defaults::STORAGE_DATE_TIME_FORMAT), $first->getCreatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        static::assertInstanceOf(\DateTimeInterface::class, $first->getUpdatedAt());
        static::assertEquals($date->format(Defaults::STORAGE_DATE_TIME_FORMAT), $first->getUpdatedAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        static::assertSame('media/foo.jpg', $first->getPath());
        static::assertFalse($first->isPrivate());
    }

    public function testHydrationForTranslation(): void
    {
        $definition = $this->definitionInstanceRegistry->get(MediaDefinition::class);

        $id = Uuid::randomBytes();

        $rows = [
            [
                'test.id' => $id,
                'test.alt' => 'foo',
                'test.translation.alt' => 'foo',
                'test.title' => 'bar',
                'test.translation.title' => 'bar',
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', Context::createDefaultContext());
        static::assertCount(1, $structs);

        /** @var MediaEntity|null $first */
        $first = $structs->first();

        static::assertInstanceOf(MediaEntity::class, $first);

        static::assertSame(Uuid::fromBytesToHex($id), $first->getId());
        static::assertSame('foo', $first->getAlt());
        static::assertSame('foo', $first->getTranslation('alt'));
        static::assertSame('bar', $first->getTitle());
        static::assertSame('bar', $first->getTranslation('title'));
    }
}
