<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentFileStorage;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Content\Product\Subscriber\ProductDocumentFileSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentFileSubscriber::class)]
class ProductDocumentFileSubscriberTest extends TestCase
{
    private const DOCUMENT = '0d0d0d0d0d0d0d0d0d0d0d0d0d0d0d0d';

    private const GERMAN = 'dededededededededededededededede';

    private const FRENCH = 'f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0';

    private const DEFAULT_FILE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const NEW_DEFAULT_FILE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const GERMAN_FILE = 'cccccccccccccccccccccccccccccccc';

    private const NEW_GERMAN_FILE = 'dddddddddddddddddddddddddddddddd';

    private const FRENCH_FILE = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    private ProductDocumentTranslationDefinition $definition;

    protected function setUp(): void
    {
        new StaticDefinitionInstanceRegistry(
            [$this->definition = new ProductDocumentTranslationDefinition(), new ProductDocumentDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }

    public function testAVersionWrittenWithoutAFileGetsTheDefaultFile(): void
    {
        $command = $this->insert(self::GERMAN);

        $this->write([$command], $this->storage([Defaults::LANGUAGE_SYSTEM => self::DEFAULT_FILE, self::FRENCH => self::FRENCH_FILE]));

        static::assertSame(self::DEFAULT_FILE, self::fileOf($command));
    }

    public function testRemovingTheFileOfAVersionGivesItTheFileAgain(): void
    {
        $command = $this->update(self::GERMAN, ['media_id' => null]);

        $this->write([$command], $this->storage([Defaults::LANGUAGE_SYSTEM => self::DEFAULT_FILE, self::GERMAN => self::GERMAN_FILE]));

        static::assertSame(self::DEFAULT_FILE, self::fileOf($command));
    }

    public function testAVersionGetsTheFileOfTheDefaultVersionWrittenWithIt(): void
    {
        $german = $this->insert(self::GERMAN);

        $this->write([$this->insert(Defaults::LANGUAGE_SYSTEM, self::DEFAULT_FILE), $german], $this->storage());

        static::assertSame(self::DEFAULT_FILE, self::fileOf($german));
    }

    public function testVersionsWithTheirOwnFileAndTheDefaultVersionStayAsWritten(): void
    {
        $german = $this->insert(self::GERMAN, self::GERMAN_FILE);
        $default = $this->insert(Defaults::LANGUAGE_SYSTEM);

        $this->write([$default, $german], $this->storage());

        static::assertSame(self::GERMAN_FILE, self::fileOf($german));
        static::assertNull(self::fileOf($default));
    }

    public function testVersionsThatStillHaveTheOldFileGetTheNewOneAfterTheWrite(): void
    {
        $storage = $this->storage([Defaults::LANGUAGE_SYSTEM => self::DEFAULT_FILE, self::GERMAN => self::DEFAULT_FILE, self::FRENCH => self::FRENCH_FILE]);

        $event = $this->write([$this->update(Defaults::LANGUAGE_SYSTEM, ['media_id' => Uuid::fromHexToBytes(self::NEW_DEFAULT_FILE)])], $storage);

        static::assertSame([], $storage->moves);
        $event->success();
        static::assertSame([self::move(self::GERMAN, self::DEFAULT_FILE, self::NEW_DEFAULT_FILE)], $storage->moves);
    }

    public function testOnlyANewDefaultFileMovesOtherVersions(): void
    {
        $storage = $this->storage([Defaults::LANGUAGE_SYSTEM => self::DEFAULT_FILE, self::GERMAN => self::GERMAN_FILE, self::FRENCH => self::GERMAN_FILE]);

        $this->write([$this->update(self::GERMAN, ['media_id' => Uuid::fromHexToBytes(self::NEW_GERMAN_FILE)])], $storage)->success();

        static::assertSame([], $storage->moves);
    }

    public function testAFollowingVersionInTheSameWriteGetsTheNewFileInItsWrite(): void
    {
        $storage = $this->storage([Defaults::LANGUAGE_SYSTEM => self::DEFAULT_FILE, self::GERMAN => self::DEFAULT_FILE]);
        $german = $this->update(self::GERMAN, ['title' => 'Handbuch']);

        $this->write([$this->update(Defaults::LANGUAGE_SYSTEM, ['media_id' => Uuid::fromHexToBytes(self::NEW_DEFAULT_FILE)]), $german], $storage)->success();

        static::assertSame(self::NEW_DEFAULT_FILE, self::fileOf($german));
        static::assertSame([], $storage->moves);
    }

    public function testADeletedVersionIsLeftAlone(): void
    {
        $storage = $this->storage([Defaults::LANGUAGE_SYSTEM => self::DEFAULT_FILE, self::GERMAN => self::DEFAULT_FILE]);
        $german = new DeleteCommand($this->definition, self::primaryKey(self::GERMAN), static::createStub(EntityExistence::class));

        $this->write([$this->update(Defaults::LANGUAGE_SYSTEM, ['media_id' => Uuid::fromHexToBytes(self::NEW_DEFAULT_FILE)]), $german], $storage)->success();

        static::assertNull(self::fileOf($german));
        static::assertSame([], $storage->moves);
    }

    public function testNothingIsReadWithoutLanguageVersions(): void
    {
        $storage = $this->storage();

        $this->write([], $storage);

        static::assertSame(0, $storage->reads);
    }

    /**
     * @param array<string, string> $files
     */
    private function storage(array $files = []): FakeProductDocumentFileStorage
    {
        return new FakeProductDocumentFileStorage(static::createStub(Connection::class), static::createStub(ClockInterface::class), $files);
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function write(array $commands, FakeProductDocumentFileStorage $storage): EntityWriteEvent
    {
        $event = EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), $commands);
        (new ProductDocumentFileSubscriber($storage))->beforeWrite($event);

        return $event;
    }

    private function insert(string $languageId, ?string $fileId = null): InsertCommand
    {
        return new InsertCommand(
            $this->definition,
            $fileId === null ? ['title' => 'Manual'] : ['title' => 'Manual', 'media_id' => Uuid::fromHexToBytes($fileId)],
            self::primaryKey($languageId),
            static::createStub(EntityExistence::class),
            '/0',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function update(string $languageId, array $payload): UpdateCommand
    {
        return new UpdateCommand($this->definition, $payload, self::primaryKey($languageId), static::createStub(EntityExistence::class), '/0');
    }

    /**
     * @return array<string, string>
     */
    private static function primaryKey(string $languageId): array
    {
        return [
            'product_document_id' => Uuid::fromHexToBytes(self::DOCUMENT),
            'product_document_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'language_id' => Uuid::fromHexToBytes($languageId),
        ];
    }

    private static function fileOf(WriteCommand $command): ?string
    {
        $fileId = $command->getPayload()['media_id'] ?? null;

        return \is_string($fileId) ? Uuid::fromBytesToHex($fileId) : null;
    }

    /**
     * @return array{documentId: string, versionId: string, languageId: string, oldFileId: string, newFileId: string}
     */
    private static function move(string $languageId, string $oldFileId, string $newFileId): array
    {
        return ['documentId' => self::DOCUMENT, 'versionId' => Defaults::LIVE_VERSION, 'languageId' => $languageId, 'oldFileId' => $oldFileId, 'newFileId' => $newFileId];
    }
}

/**
 * Keeps the files of one document version in memory and records the moves.
 *
 * @internal
 */
class FakeProductDocumentFileStorage extends ProductDocumentFileStorage
{
    /**
     * @var list<array{documentId: string, versionId: string, languageId: string, oldFileId: string, newFileId: string}>
     */
    public array $moves = [];

    public int $reads = 0;

    /**
     * @param array<string, string> $files file id per language id
     */
    public function __construct(
        Connection $connection,
        ClockInterface $clock,
        private readonly array $files = [],
    ) {
        parent::__construct($connection, $clock);
    }

    public function getFiles(array $documentIds, array $versionIds): array
    {
        ++$this->reads;

        return $this->files === [] ? [] : [$documentIds[0] => [$versionIds[0] => $this->files]];
    }

    public function moveFiles(array $moves): void
    {
        array_push($this->moves, ...$moves);
    }
}
