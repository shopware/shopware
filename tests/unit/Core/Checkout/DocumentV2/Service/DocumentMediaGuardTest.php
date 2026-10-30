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

    public function testAcceptsPublicMediaOfAnotherFolderWithoutResolvingTheDocumentFolder(): void
    {
        $repository = StaticEntityRepository::of(MediaFolderCollection::class, [[Uuid::randomHex()]]);
        $guard = new DocumentMediaGuard($repository);

        $guard->assertServable($this->createMedia(Uuid::randomHex(), private: false), $this->context);

        static::assertCount(1, $repository->searches);
    }

    public function testAcceptsPublicMediaWithoutAFolder(): void
    {
        $guard = new DocumentMediaGuard(StaticEntityRepository::of(MediaFolderCollection::class, [[Uuid::randomHex()]]));

        $guard->assertServable($this->createMedia(null, private: false), $this->context);

        $this->expectNotToPerformAssertions();
    }

    public function testAcceptsPrivateMediaOfTheDocumentFolder(): void
    {
        $folderId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(MediaFolderCollection::class, [[$folderId]]);
        $guard = new DocumentMediaGuard($repository);

        $guard->assertServable($this->createMedia($folderId, private: true), $this->context);

        static::assertSame([], $repository->searches);
    }

    public function testRejectsPrivateMediaOfAnotherFolder(): void
    {
        $media = $this->createMedia(Uuid::randomHex(), private: true);
        $guard = new DocumentMediaGuard(StaticEntityRepository::of(MediaFolderCollection::class, [[Uuid::randomHex()]]));

        static::expectExceptionObject(DocumentV2Exception::documentMediaNotAllowed($media->getId()));

        $guard->assertServable($media, $this->context);
    }

    public function testRejectsPrivateMediaWithoutAFolder(): void
    {
        $media = $this->createMedia(null, private: true);
        $guard = new DocumentMediaGuard(StaticEntityRepository::of(MediaFolderCollection::class, [[Uuid::randomHex()]]));

        static::expectExceptionObject(DocumentV2Exception::documentMediaNotAllowed($media->getId()));

        $guard->assertServable($media, $this->context);
    }

    public function testRejectsPrivateMediaWithoutAFolderWhenTheDocumentFolderIsMissing(): void
    {
        $media = $this->createMedia(null, private: true);
        $guard = new DocumentMediaGuard(StaticEntityRepository::of(MediaFolderCollection::class, [[]]));

        static::expectExceptionObject(DocumentV2Exception::documentMediaNotAllowed($media->getId()));

        $guard->assertServable($media, $this->context);
    }

    public function testIsServableReportsWithoutThrowing(): void
    {
        $folderId = Uuid::randomHex();
        $guard = new DocumentMediaGuard(StaticEntityRepository::of(MediaFolderCollection::class, [[$folderId]]));

        static::assertTrue($guard->isServable($this->createMedia(Uuid::randomHex(), private: false), $this->context));
        static::assertTrue($guard->isServable($this->createMedia($folderId, private: true), $this->context));
        static::assertFalse($guard->isServable($this->createMedia(Uuid::randomHex(), private: true), $this->context));
    }

    public function testResolvesTheDocumentFolderOnlyOnce(): void
    {
        $folderId = Uuid::randomHex();
        $repository = StaticEntityRepository::of(MediaFolderCollection::class, [[$folderId]]);
        $guard = new DocumentMediaGuard($repository);

        $guard->assertServable($this->createMedia($folderId, private: true), $this->context);
        $guard->assertServable($this->createMedia($folderId, private: true), $this->context);

        static::assertSame([], $repository->searches);
    }

    public function testResetResolvesTheDocumentFolderAgain(): void
    {
        $firstFolderId = Uuid::randomHex();
        $secondFolderId = Uuid::randomHex();
        $guard = new DocumentMediaGuard(
            StaticEntityRepository::of(MediaFolderCollection::class, [[$firstFolderId], [$secondFolderId]]),
        );

        $guard->assertServable($this->createMedia($firstFolderId, private: true), $this->context);

        $guard->reset();

        $guard->assertServable($this->createMedia($secondFolderId, private: true), $this->context);

        $media = $this->createMedia($firstFolderId, private: true);

        static::expectExceptionObject(DocumentV2Exception::documentMediaNotAllowed($media->getId()));

        $guard->assertServable($media, $this->context);
    }

    private function createMedia(?string $mediaFolderId, bool $private): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setPrivate($private);

        if ($mediaFolderId !== null) {
            $media->setMediaFolderId($mediaFolderId);
        }

        return $media;
    }
}
