<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Content\Product\Subscriber\ProductDocumentTitleSubscriber;
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
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentTitleSubscriber::class)]
class ProductDocumentTitleSubscriberTest extends TestCase
{
    private IdsCollection $ids;

    private ProductDocumentTranslationDefinition $definition;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        new StaticDefinitionInstanceRegistry(
            [$this->definition = new ProductDocumentTranslationDefinition(), new ProductDocumentDefinition(), new LanguageDefinition(), new MediaDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testSubscribesToTheWriteEvent(): void
    {
        static::assertSame([EntityWriteEvent::class => 'setTitleFromFileName'], ProductDocumentTitleSubscriber::getSubscribedEvents());
    }

    public function testANewDocumentWithoutTitleIsNamedAfterItsFile(): void
    {
        $translation = $this->newTranslation(Defaults::LANGUAGE_SYSTEM, ['media_id' => $this->ids->getBytes('manual')]);

        $this->write([$translation], [$this->ids->getBytes('manual') => 'operating-manual']);

        static::assertSame('operating-manual', $translation->getPayload()['title']);
    }

    public function testAGivenTitleIsKept(): void
    {
        $translation = $this->newTranslation(Defaults::LANGUAGE_SYSTEM, ['title' => 'Manual', 'media_id' => $this->ids->getBytes('manual')]);

        $this->write([$translation], [$this->ids->getBytes('manual') => 'operating-manual']);

        static::assertSame('Manual', $translation->getPayload()['title']);
    }

    public function testReplacingTheFileKeepsTheTitle(): void
    {
        $translation = new UpdateCommand(
            $this->definition,
            ['media_id' => $this->ids->getBytes('manual-v2')],
            $this->primaryKey(Defaults::LANGUAGE_SYSTEM),
            static::createStub(EntityExistence::class),
            '/0'
        );

        $this->write([$translation], [$this->ids->getBytes('manual-v2') => 'operating-manual-v2']);

        static::assertArrayNotHasKey('title', $translation->getPayload());
    }

    public function testTranslationsInOtherLanguagesKeepAnEmptyTitle(): void
    {
        $translation = $this->newTranslation($this->ids->get('german'), ['media_id' => $this->ids->getBytes('handbuch')]);

        $this->write([$translation], [$this->ids->getBytes('handbuch') => 'bedienungsanleitung']);

        static::assertArrayNotHasKey('title', $translation->getPayload());
    }

    public function testAFileWithoutNameLeavesTheTitleEmpty(): void
    {
        $translation = $this->newTranslation(Defaults::LANGUAGE_SYSTEM, ['media_id' => $this->ids->getBytes('manual')]);

        $this->write([$translation], []);

        static::assertArrayNotHasKey('title', $translation->getPayload());
    }

    public function testALongFileNameIsShortenedToTheLengthOfATitle(): void
    {
        $translation = $this->newTranslation(Defaults::LANGUAGE_SYSTEM, ['media_id' => $this->ids->getBytes('manual')]);

        $this->write([$translation], [$this->ids->getBytes('manual') => str_repeat('ä', 300)]);

        static::assertSame(str_repeat('ä', 255), $translation->getPayload()['title']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function newTranslation(string $languageId, array $payload): InsertCommand
    {
        return new InsertCommand(
            $this->definition,
            $payload,
            $this->primaryKey($languageId),
            static::createStub(EntityExistence::class),
            '/0'
        );
    }

    /**
     * @return array<string, string>
     */
    private function primaryKey(string $languageId): array
    {
        return [
            'product_document_id' => $this->ids->getBytes('document'),
            'product_document_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'language_id' => Uuid::fromHexToBytes($languageId),
        ];
    }

    /**
     * @param list<WriteCommand> $commands
     * @param array<string, string> $fileNames file name by binary media id, as the database returns them
     */
    private function write(array $commands, array $fileNames): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn($fileNames);

        $event = EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), $commands);
        (new ProductDocumentTitleSubscriber($connection))->setTitleFromFileName($event);
    }
}
