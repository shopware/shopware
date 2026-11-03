<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\InvalidateProductCache;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        static::getContainer()->get('media.repository')->create([$this->file('manual')], Context::createDefaultContext());
    }

    public function testLanguageVersionsWithoutTheirOwnFileFollowTheFileOfTheDefaultLanguage(): void
    {
        $germanId = $this->getDeDeLanguageId();
        static::getContainer()->get('media.repository')->create([$this->file('datasheet'), $this->file('handbuch')], Context::createDefaultContext());
        $this->createDrillWithBlueVariant();
        $documents = static::getContainer()->get('product_document.repository');
        $document = ['id' => $this->ids->create('document'), 'productId' => $this->ids->get('drill'), ...$this->manual(), 'translations' => [$germanId => ['title' => 'Handbuch']]];

        $documents->create([$document], Context::createDefaultContext());
        static::assertEquals([Defaults::LANGUAGE_SYSTEM => $this->ids->get('manual'), $germanId => $this->ids->get('manual')], $this->getFiles());

        $documents->update([['id' => $this->ids->get('document'), 'mediaId' => $this->ids->get('datasheet')]], Context::createDefaultContext());
        static::assertEquals([Defaults::LANGUAGE_SYSTEM => $this->ids->get('datasheet'), $germanId => $this->ids->get('datasheet')], $this->getFiles());

        $documents->update([['id' => $this->ids->get('document'), 'translations' => [$germanId => ['mediaId' => $this->ids->get('handbuch')]]]], Context::createDefaultContext());
        $documents->update([['id' => $this->ids->get('document'), 'mediaId' => $this->ids->get('manual')]], Context::createDefaultContext());
        static::assertEquals([Defaults::LANGUAGE_SYSTEM => $this->ids->get('manual'), $germanId => $this->ids->get('handbuch')], $this->getFiles());
    }

    public function testDocumentRoutesOfTheAdminApiInvalidateTheProductAndItsVariants(): void
    {
        $this->createDrillWithBlueVariant();
        $document = ['id' => $this->ids->create('document'), 'productId' => $this->ids->get('drill'), ...$this->manual()];

        $created = $this->collectInvalidatedProductIds('POST', '/api/product-document', $document);
        static::assertContains($this->ids->get('drill'), $created);
        static::assertContains($this->ids->get('drill.blue'), $created);

        $updated = $this->collectInvalidatedProductIds('PATCH', '/api/product-document/' . $this->ids->get('document'), ['title' => 'Manual v2']);
        static::assertContains($this->ids->get('drill'), $updated);
        static::assertContains($this->ids->get('drill.blue'), $updated);

        $translated = $this->collectInvalidatedProductIds(
            'PATCH',
            '/api/product-document/' . $this->ids->get('document'),
            ['title' => 'Handbuch'],
            ['HTTP_SW_LANGUAGE_ID' => $this->getDeDeLanguageId()],
        );
        static::assertContains($this->ids->get('drill'), $translated);
        static::assertContains($this->ids->get('drill.blue'), $translated);

        $deleted = $this->collectInvalidatedProductIds('DELETE', '/api/product-document/' . $this->ids->get('document'));
        static::assertContains($this->ids->get('drill'), $deleted);
        static::assertContains($this->ids->get('drill.blue'), $deleted);
    }

    /**
     * @return array{id: string, fileName: string, fileExtension: string, mimeType: string}
     */
    private function file(string $name): array
    {
        return ['id' => $this->ids->create($name), 'fileName' => $name, 'fileExtension' => 'pdf', 'mimeType' => 'application/pdf'];
    }

    /**
     * @return array<string, string> file id per language id of the document
     */
    private function getFiles(): array
    {
        /** @var array<string, string> */
        return static::getContainer()->get(Connection::class)->fetchAllKeyValue(
            'SELECT LOWER(HEX(`language_id`)), LOWER(HEX(`media_id`)) FROM `product_document_translation` WHERE `product_document_id` = :id',
            ['id' => Uuid::fromHexToBytes($this->ids->get('document'))]
        );
    }

    /**
     * @return array{title: string, mediaId: string}
     */
    private function manual(): array
    {
        return ['title' => 'Manual', 'mediaId' => $this->ids->get('manual')];
    }

    private function createDrillWithBlueVariant(): void
    {
        $product = (new ProductBuilder($this->ids, 'drill'))
            ->price(10)
            ->variant((new ProductBuilder($this->ids, 'drill.blue'))->build())
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
    }

    /**
     * @param array<mixed> $body
     * @param array<string, string> $server
     *
     * @return list<string>
     */
    private function collectInvalidatedProductIds(string $method, string $url, array $body = [], array $server = []): array
    {
        $invalidatedIds = [];
        $hook = static function (InvalidateProductCache $event) use (&$invalidatedIds): void {
            array_push($invalidatedIds, ...$event->getIds());
        };

        $dispatcher = EventHookDispatcher::fromContainer(static::getContainer());
        $dispatcher->on(InvalidateProductCache::class, $hook);
        $this->getBrowser()->request($method, $url, server: ['CONTENT_TYPE' => 'application/json', ...$server], content: json_encode($body, \JSON_THROW_ON_ERROR));
        $dispatcher->off(InvalidateProductCache::class, $hook);

        $response = $this->getBrowser()->getResponse();
        static::assertTrue($response->isSuccessful(), $method . ' ' . $url . ': ' . $response->getContent());

        return $invalidatedIds;
    }
}
