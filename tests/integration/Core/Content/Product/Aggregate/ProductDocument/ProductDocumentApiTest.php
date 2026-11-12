<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentValidator;
use Shopware\Core\Content\Product\Events\InvalidateProductCache;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
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

        static::getContainer()->get('media.repository')->create([[
            'id' => $this->ids->create('manual'),
            'fileName' => 'manual',
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
            'fileSize' => 1024,
        ]], Context::createDefaultContext());
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

    public function testADocumentCannotMoveToAnotherProduct(): void
    {
        $this->createDrillWithBlueVariant();
        $this->getBrowser()->request('POST', '/api/product-document', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['id' => $this->ids->create('document'), 'productId' => $this->ids->get('drill'), ...$this->manual()],
            \JSON_THROW_ON_ERROR,
        ));
        static::assertTrue($this->getBrowser()->getResponse()->isSuccessful());

        $url = '/api/product-document/' . $this->ids->get('document');
        $this->getBrowser()->request('PATCH', $url, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['productId' => $this->ids->get('drill')], \JSON_THROW_ON_ERROR));
        static::assertTrue($this->getBrowser()->getResponse()->isSuccessful(), 'the same product can be sent again');

        $this->getBrowser()->request('PATCH', $url, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['productId' => $this->ids->get('drill.blue')], \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        static::assertSame(400, $response->getStatusCode());
        $error = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['errors'][0];
        static::assertSame('The field "product_id" of "product_document" is immutable and cannot be updated.', $error['detail']);
    }

    public function testADocumentCannotBeCreatedWithoutAFileInTheDefaultLanguage(): void
    {
        $this->createDrillWithBlueVariant();

        foreach ([[], ['mediaId' => $this->ids->get('manual')]] as $germanFields) {
            $this->getBrowser()->request(
                'POST',
                '/api/product-document',
                server: ['CONTENT_TYPE' => 'application/json', 'HTTP_SW_LANGUAGE_ID' => $this->getDeDeLanguageId()],
                content: json_encode(['productId' => $this->ids->get('drill'), ...$germanFields], \JSON_THROW_ON_ERROR),
            );

            static::assertSame(
                [ProductDocumentValidator::VIOLATION_DEFAULT_LANGUAGE_FILE_MISSING],
                $this->errorCodes(),
                (string) $this->getBrowser()->getResponse()->getContent(),
            );
        }
    }

    public function testTheFileOfADocumentCannotBeReplacedByAFileOfAnotherType(): void
    {
        $this->createDocumentWithManual();
        $file = __DIR__ . '/../../../Media/fixtures/file_example.mp3';

        $this->getBrowser()->request(
            'POST',
            '/api/_action/media/' . $this->ids->get('manual') . '/upload?extension=mp3&fileName=manual',
            server: ['HTTP_CONTENT-TYPE' => 'audio/mpeg', 'HTTP_CONTENT-LENGTH' => filesize($file)],
            content: (string) file_get_contents($file),
        );

        static::assertSame([ProductDocumentValidator::VIOLATION_FILE_EXTENSION_NOT_ALLOWED], $this->errorCodes());
    }

    public function testTheFileOfADocumentCannotBeMadePrivate(): void
    {
        $this->createDocumentWithManual();

        $this->getBrowser()->jsonRequest('PATCH', '/api/media/' . $this->ids->get('manual'), ['private' => true]);

        static::assertSame([ProductDocumentValidator::VIOLATION_FILE_PRIVATE], $this->errorCodes());
    }

    /**
     * @return array{title: string, mediaId: string}
     */
    private function manual(): array
    {
        return ['title' => 'Manual', 'mediaId' => $this->ids->get('manual')];
    }

    private function createDocumentWithManual(): void
    {
        $this->createDrillWithBlueVariant();
        static::getContainer()->get('product_document.repository')->create(
            [['productId' => $this->ids->get('drill'), ...$this->manual()]],
            Context::createDefaultContext(),
        );
    }

    /**
     * @return list<string>
     */
    private function errorCodes(): array
    {
        $response = $this->getBrowser()->getResponse();
        static::assertSame(400, $response->getStatusCode(), (string) $response->getContent());

        return array_column(json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['errors'], 'code');
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
