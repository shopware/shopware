<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentTitleSubscriberTest extends TestCase
{
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    /**
     * @var EntityRepository<ProductDocumentCollection>
     */
    private EntityRepository $productDocumentRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
        $this->productDocumentRepository = static::getContainer()->get('product_document.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');

        static::getContainer()->get('product.repository')->create([(new ProductBuilder($this->ids, 'drill'))->price(10)->build()], $this->context);
    }

    public function testADocumentCreatedThroughTheAdminApiWithoutTitleIsNamedAfterItsFile(): void
    {
        $this->request('POST', '/api/product-document', [
            'id' => $this->ids->create('document'),
            'productId' => $this->ids->get('drill'),
            'mediaId' => $this->createMedia('operating-manual'),
        ]);

        static::assertSame([Defaults::LANGUAGE_SYSTEM => 'operating-manual'], $this->titles());
    }

    public function testADocumentCreatedThroughTheSyncApiWithoutTitleIsNamedAfterItsFile(): void
    {
        $this->request('POST', '/api/_action/sync', [[
            'key' => 'documents',
            'action' => SyncController::ACTION_UPSERT,
            'entity' => ProductDocumentDefinition::ENTITY_NAME,
            'payload' => [[
                'id' => $this->ids->create('document'),
                'productId' => $this->ids->get('drill'),
                'mediaId' => $this->createMedia('operating-manual'),
            ]],
        ]]);

        static::assertSame([Defaults::LANGUAGE_SYSTEM => 'operating-manual'], $this->titles());
    }

    public function testAGivenTitleIsKept(): void
    {
        $this->createDocument(['title' => 'Manual', 'mediaId' => $this->createMedia('operating-manual')]);

        static::assertSame([Defaults::LANGUAGE_SYSTEM => 'Manual'], $this->titles());
    }

    public function testVersionsInOtherLanguagesKeepTheirEmptyTitleToFallBackToTheDefaultOne(): void
    {
        $germanId = $this->getDeDeLanguageId();

        $this->createDocument(['translations' => [
            Defaults::LANGUAGE_SYSTEM => ['mediaId' => $this->createMedia('operating-manual')],
            $germanId => ['mediaId' => $this->createMedia('bedienungsanleitung')],
        ]]);

        $titles = $this->titles();
        ksort($titles);
        $expected = [Defaults::LANGUAGE_SYSTEM => 'operating-manual', $germanId => null];
        ksort($expected);
        static::assertSame($expected, $titles);
    }

    public function testALongFileNameIsShortenedToTheLengthOfATitle(): void
    {
        $this->createDocument(['mediaId' => $this->createMedia(str_repeat('a', 300))]);

        static::assertSame([Defaults::LANGUAGE_SYSTEM => str_repeat('a', 255)], $this->titles());
    }

    /**
     * @param array<string, mixed> $document
     */
    private function createDocument(array $document): void
    {
        $this->productDocumentRepository->create([
            ['id' => $this->ids->create('document'), 'productId' => $this->ids->get('drill'), ...$document],
        ], $this->context);
    }

    private function createMedia(string $fileName): string
    {
        $this->mediaRepository->create([[
            'id' => $this->ids->create($fileName),
            'fileName' => $fileName,
            'fileExtension' => 'pdf',
            'fileSize' => 1024,
        ]], $this->context);

        return $this->ids->get($fileName);
    }

    /**
     * @param array<mixed> $body
     */
    private function request(string $method, string $url, array $body): void
    {
        $this->getBrowser()->request($method, $url, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, \JSON_THROW_ON_ERROR));

        $response = $this->getBrowser()->getResponse();
        static::assertTrue($response->isSuccessful(), $method . ' ' . $url . ': ' . $response->getContent());
    }

    /**
     * @return array<string, string|null> stored title by language id
     */
    private function titles(): array
    {
        $criteria = new Criteria([$this->ids->get('document')]);
        $criteria->addAssociation('translations');

        $translations = $this->productDocumentRepository->search($criteria, $this->context)->getEntities()->first()?->getTranslations();
        static::assertNotNull($translations);

        $titles = [];
        foreach ($translations as $translation) {
            static::assertInstanceOf(ProductDocumentTranslationEntity::class, $translation);
            $titles[$translation->getLanguageId()] = $translation->getTitle();
        }

        return $titles;
    }
}
