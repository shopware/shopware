<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaPathWriteValidator;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaPathWriteValidator::class)]
class MediaPathWriteValidatorTest extends TestCase
{
    private MediaDefinition $mediaDefinition;

    private MediaThumbnailDefinition $thumbnailDefinition;

    private MediaPathWriteValidator $validator;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [MediaDefinition::class, MediaThumbnailDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $mediaDefinition = $registry->get(MediaDefinition::class);
        static::assertInstanceOf(MediaDefinition::class, $mediaDefinition);
        $this->mediaDefinition = $mediaDefinition;

        $thumbnailDefinition = $registry->get(MediaThumbnailDefinition::class);
        static::assertInstanceOf(MediaThumbnailDefinition::class, $thumbnailDefinition);
        $this->thumbnailDefinition = $thumbnailDefinition;

        $this->validator = $this->createValidator();
    }

    #[DataProvider('rejectedPathProvider')]
    public function testRejectsPathOnInsertThatIsNoStrictExternalUrl(string $path): void
    {
        $event = $this->createEvent($this->apiContext(), $this->insert($this->mediaDefinition, ['path' => $path]));

        $this->validator->preValidate($event);

        $this->assertViolation($event, MediaException::MEDIA_ILLEGAL_PATH);
    }

    public static function rejectedPathProvider(): \Generator
    {
        yield 'relative path into the private filesystem' => ['document/invoice.pdf'];
        yield 'external looking url that resolves to a local file' => ['https://cdn.example.com/../../document/invoice.pdf'];
        yield 'empty string' => [''];
    }

    public function testAllowsStrictExternalUrlOnInsert(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->insert($this->mediaDefinition, ['path' => 'https://cdn.example.com/photo/2024/sunflowers.jpg']));

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testAllowsStrictExternalUrlOnUpdate(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['path' => 'https://cdn.example.com/photo/sunflowers_v2.jpg']));

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testRejectsRelativePathOnUpdate(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['path' => 'document/invoice.pdf']));

        $this->validator->preValidate($event);

        $this->assertViolation($event, MediaException::MEDIA_ILLEGAL_PATH);
    }

    public function testAllowsUnchangedRelativePathOnUpdate(): void
    {
        $id = Uuid::randomHex();
        $validator = $this->createValidator([$id => 'media/ab/cd/foo.png']);

        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['path' => 'media/ab/cd/foo.png'], $id));

        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testRejectsChangedRelativePathOnUpdate(): void
    {
        $id = Uuid::randomHex();
        $validator = $this->createValidator([$id => 'media/ab/cd/foo.png']);

        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['path' => 'document/invoice.pdf'], $id));

        $validator->preValidate($event);

        $this->assertViolation($event, MediaException::MEDIA_ILLEGAL_PATH);
    }

    public function testAllowsUnchangedRelativeThumbnailPathOnUpdate(): void
    {
        $id = Uuid::randomHex();
        $validator = $this->createValidator([$id => 'thumbnail/ab/cd/foo_100x100.png']);

        $event = $this->createEvent($this->apiContext(), $this->update($this->thumbnailDefinition, ['path' => 'thumbnail/ab/cd/foo_100x100.png'], $id));

        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testRejectsNullOnUpdate(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['path' => null]));

        $this->validator->preValidate($event);

        $this->assertViolation($event, MediaException::MEDIA_ILLEGAL_PATH);
    }

    public function testAllowsNullOnInsertBecauseItEqualsOmittingThePath(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->insert($this->mediaDefinition, ['path' => null]));

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testRejectsRelativeThumbnailPath(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->update($this->thumbnailDefinition, ['path' => 'document/invoice.pdf']));

        $this->validator->preValidate($event);

        $this->assertViolation($event, MediaException::MEDIA_ILLEGAL_PATH);
    }

    public function testAllowsExternalThumbnailUrl(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->update($this->thumbnailDefinition, ['path' => 'https://cdn.example.com/photo/sunflowers_100x100.jpg']));

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[DataProvider('illegalFileNameProvider')]
    public function testRejectsIllegalFileName(string $fileName): void
    {
        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['file_name' => $fileName]));

        $this->validator->preValidate($event);

        $this->assertViolation($event, MediaException::MEDIA_ILLEGAL_FILE_NAME);
    }

    public static function illegalFileNameProvider(): \Generator
    {
        yield 'dot-dot segments would place the generated path outside the media directory' => ['../../../../evil'];
        yield 'slashes would add directories to the generated path' => ['theme/evil'];
        yield 'backslashes are normalized to separators' => ['..\\..\\evil'];
    }

    public function testReportsTheSpecificFileNameErrorCode(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['file_name' => '']));

        $this->validator->preValidate($event);

        $this->assertViolation($event, MediaException::MEDIA_EMPTY_FILE_NAME);
    }

    public function testRejectsIllegalFileNameOnInsert(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->insert($this->mediaDefinition, ['file_name' => '../../evil']));

        $this->validator->preValidate($event);

        $this->assertViolation($event, MediaException::MEDIA_ILLEGAL_FILE_NAME);
    }

    public function testAllowsUnchangedFileNameOnUpdateThatPredatesTheValidation(): void
    {
        $id = Uuid::randomHex();
        $validator = $this->createValidator(storedFileNames: [$id => 'Ben & Jerry\'s']);

        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['file_name' => 'Ben & Jerry\'s'], $id));

        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testRejectsChangedIllegalFileNameOnUpdate(): void
    {
        $id = Uuid::randomHex();
        $validator = $this->createValidator(storedFileNames: [$id => 'Ben & Jerry\'s']);

        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['file_name' => '../../evil'], $id));

        $validator->preValidate($event);

        $this->assertViolation($event, MediaException::MEDIA_ILLEGAL_FILE_NAME);
    }

    public function testAllowsRegularFileName(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['file_name' => 'summer sale 2024 (final)']));

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresWritesWithoutPath(): void
    {
        $event = $this->createEvent($this->apiContext(), $this->update($this->mediaDefinition, ['title' => 'foo']));

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresDeleteCommands(): void
    {
        $delete = new DeleteCommand(
            $this->mediaDefinition,
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class)
        );

        $event = $this->createEvent($this->apiContext(), $delete);

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testAllowsAnyPathAndFileNameInSystemScope(): void
    {
        $event = $this->createEvent(Context::createDefaultContext(), $this->update($this->mediaDefinition, ['path' => 'media/ab/cd/foo.png', 'file_name' => '../foo']));

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    private function assertViolation(PreWriteValidationEvent $event, string $code): void
    {
        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);

        $exception = $exceptions[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame($code, $exception->getViolations()->get(0)->getCode());
    }

    /**
     * @param array<string, string|null> $storedPaths
     * @param array<string, string|null> $storedFileNames
     */
    private function createValidator(array $storedPaths = [], array $storedFileNames = []): MediaPathWriteValidator
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturnCallback(
            static fn (string $query): array => str_contains($query, '`file_name`') ? $storedFileNames : $storedPaths
        );

        return new MediaPathWriteValidator($connection);
    }

    private function apiContext(): Context
    {
        return new Context(new AdminApiSource(null, null));
    }

    private function createEvent(Context $context, WriteCommand $command): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(WriteContext::createFromContext($context), [$command]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insert(EntityDefinition $definition, array $payload): InsertCommand
    {
        return new InsertCommand(
            $definition,
            $payload,
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0'
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function update(EntityDefinition $definition, array $payload, ?string $id = null): UpdateCommand
    {
        return new UpdateCommand(
            $definition,
            $payload,
            ['id' => Uuid::fromHexToBytes($id ?? Uuid::randomHex())],
            static::createStub(EntityExistence::class),
            '/0'
        );
    }
}
