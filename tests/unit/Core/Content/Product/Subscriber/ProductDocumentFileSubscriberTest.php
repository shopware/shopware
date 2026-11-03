<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentFileStorage;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Content\Product\Subscriber\ProductDocumentFileSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
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

    private const DEFAULT_FILE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const NEW_DEFAULT_FILE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const GERMAN_FILE = 'cccccccccccccccccccccccccccccccc';

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

        $this->write([$command], $this->storage(self::DEFAULT_FILE));

        static::assertSame(self::DEFAULT_FILE, self::fileOf($command));
    }

    public function testRemovingTheFileOfAVersionGivesItTheDefaultFile(): void
    {
        $command = $this->update(self::GERMAN, ['media_id' => null]);

        $this->write([$command], $this->storage(self::DEFAULT_FILE));

        static::assertSame(self::DEFAULT_FILE, self::fileOf($command));
    }

    public function testAVersionGetsTheFileOfTheDefaultVersionWrittenWithIt(): void
    {
        $storage = $this->storage(self::DEFAULT_FILE);
        $german = $this->insert(self::GERMAN);

        $this->write([$german, $this->update(Defaults::LANGUAGE_SYSTEM, ['media_id' => Uuid::fromHexToBytes(self::NEW_DEFAULT_FILE)])], $storage);

        static::assertSame(self::NEW_DEFAULT_FILE, self::fileOf($german));
        static::assertSame(0, $storage->reads);
    }

    public function testVersionsWithTheirOwnFileAndTheDefaultVersionStayAsWritten(): void
    {
        $german = $this->insert(self::GERMAN, self::GERMAN_FILE);
        $default = $this->insert(Defaults::LANGUAGE_SYSTEM);

        $this->write([$default, $german], $this->storage(self::DEFAULT_FILE));

        static::assertSame(self::GERMAN_FILE, self::fileOf($german));
        static::assertNull(self::fileOf($default));
    }

    public function testVersionsKeepTheirFileWhenTheDefaultLanguageGetsAnotherOne(): void
    {
        $german = $this->update(self::GERMAN, ['title' => 'Handbuch']);

        $this->write([$this->update(Defaults::LANGUAGE_SYSTEM, ['media_id' => Uuid::fromHexToBytes(self::NEW_DEFAULT_FILE)]), $german], $this->storage(self::DEFAULT_FILE));

        static::assertNull(self::fileOf($german));
    }

    public function testNothingIsReadWhenNoVersionIsWithoutAFile(): void
    {
        $storage = $this->storage(self::DEFAULT_FILE);

        $this->write([$this->update(self::GERMAN, ['title' => 'Handbuch'])], $storage);

        static::assertSame(0, $storage->reads);
    }

    private function storage(string $defaultFile): FakeProductDocumentFileStorage
    {
        return new FakeProductDocumentFileStorage(static::createStub(Connection::class), $defaultFile);
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function write(array $commands, FakeProductDocumentFileStorage $storage): void
    {
        $event = EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), $commands);
        (new ProductDocumentFileSubscriber($storage))->beforeWrite($event);
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
}

/**
 * Returns the same default file for every document and counts the reads.
 *
 * @internal
 */
class FakeProductDocumentFileStorage extends ProductDocumentFileStorage
{
    public int $reads = 0;

    public function __construct(
        Connection $connection,
        private readonly string $defaultFile,
    ) {
        parent::__construct($connection);
    }

    public function getDefaultFiles(array $documentIds, string $versionId): array
    {
        ++$this->reads;

        return array_fill_keys($documentIds, $this->defaultFile);
    }
}
