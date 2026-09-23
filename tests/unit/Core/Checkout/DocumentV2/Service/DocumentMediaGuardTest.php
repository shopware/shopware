<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentMediaGuard;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentMediaGuard::class)]
class DocumentMediaGuardTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testGetFolderIdResolvesTheDocumentDefaultFolder(): void
    {
        $folderId = Uuid::randomHex();
        $guard = new DocumentMediaGuard(StaticEntityRepository::of(MediaFolderCollection::class, [[$folderId]]));

        static::assertSame($folderId, $guard->getFolderId($this->context));
    }

    public function testGetFolderIdIsResolvedOnlyOnce(): void
    {
        $folderId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(MediaFolderCollection::class, [[$folderId]]);
        $guard = new DocumentMediaGuard($repository);

        static::assertSame($folderId, $guard->getFolderId($this->context));
        static::assertSame($folderId, $guard->getFolderId($this->context));
        static::assertSame([], $repository->searches);
    }

    public function testAnUnresolvableFolderIsCachedAsWell(): void
    {
        $repository = StaticEntityRepository::of(MediaFolderCollection::class, [[]]);
        $guard = new DocumentMediaGuard($repository);

        static::assertNull($guard->getFolderId($this->context));
        static::assertNull($guard->getFolderId($this->context));
        static::assertSame([], $repository->searches);
    }

    public function testResetResolvesTheFolderAgain(): void
    {
        $firstFolderId = Uuid::randomHex();
        $secondFolderId = Uuid::randomHex();
        $guard = new DocumentMediaGuard(
            StaticEntityRepository::of(MediaFolderCollection::class, [[$firstFolderId], [$secondFolderId]]),
        );

        static::assertSame($firstFolderId, $guard->getFolderId($this->context));

        $guard->reset();

        static::assertSame($secondFolderId, $guard->getFolderId($this->context));
    }

    public function testAssertIsDocumentMediaAcceptsMediaOfTheDocumentFolder(): void
    {
        $folderId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(MediaFolderCollection::class, [[$folderId]]);
        $guard = new DocumentMediaGuard($repository);

        $guard->assertIsDocumentMedia($this->createMedia($folderId), $this->context);

        static::assertSame([], $repository->searches);
    }

    public function testAssertIsDocumentMediaRejectsMediaOfAnotherFolder(): void
    {
        $media = $this->createMedia(Uuid::randomHex());
        $guard = new DocumentMediaGuard(
            StaticEntityRepository::of(MediaFolderCollection::class, [[Uuid::randomHex()]]),
        );

        static::expectExceptionObject(DocumentV2Exception::documentMediaNotAllowed($media->getId()));

        $guard->assertIsDocumentMedia($media, $this->context);
    }

    public function testAssertIsDocumentMediaRejectsMediaWithoutAFolder(): void
    {
        $media = $this->createMedia(null);
        $guard = new DocumentMediaGuard(
            StaticEntityRepository::of(MediaFolderCollection::class, [[Uuid::randomHex()]]),
        );

        static::expectExceptionObject(DocumentV2Exception::documentMediaNotAllowed($media->getId()));

        $guard->assertIsDocumentMedia($media, $this->context);
    }

    public function testAssertIsDocumentMediaAcceptsMediaWithoutAFolderWhenTheFolderIsMissing(): void
    {
        $repository = StaticEntityRepository::of(MediaFolderCollection::class, [[]]);
        $guard = new DocumentMediaGuard($repository);

        $guard->assertIsDocumentMedia($this->createMedia(null), $this->context);

        static::assertSame([], $repository->searches);
    }

    private function createMedia(?string $mediaFolderId): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());

        if ($mediaFolderId !== null) {
            $media->setMediaFolderId($mediaFolderId);
        }

        return $media;
    }
}
