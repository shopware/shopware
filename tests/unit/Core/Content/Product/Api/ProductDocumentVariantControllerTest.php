<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Api\ProductDocumentVariantController;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentVariantController::class)]
class ProductDocumentVariantControllerTest extends TestCase
{
    private const PRODUCT_ID = '0191a5f4b4a07a1f8e1d2c3b4a596877';

    private const MEDIA_ID = '0191a5f4b4a07a1f8e1d2c3b4a596878';

    /**
     * @var StaticEntityRepository<ProductDocumentCollection>
     */
    private StaticEntityRepository $documentRepository;

    private ProductDocumentVariantController $controller;

    protected function setUp(): void
    {
        $this->documentRepository = StaticEntityRepository::of(ProductDocumentCollection::class);

        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $operations): mixed => $operations());

        $this->controller = new ProductDocumentVariantController($this->documentRepository, $connection);
    }

    public function testDryRunReturnsMatchingVariantsWithoutWriting(): void
    {
        $variantDocument = $this->createVariantDocument('variant-a');
        $this->documentRepository->addSearch(new ProductDocumentCollection([$variantDocument]));

        $response = $this->controller->sync(
            self::PRODUCT_ID,
            $this->createRequest([['mediaId' => self::MEDIA_ID, 'title' => 'Manual', 'changes' => ['title' => 'Guide']]], dryRun: true),
            Context::createDefaultContext(),
        );

        static::assertSame(['variantIds' => ['variant-a']], json_decode((string) $response->getContent(), true));
        static::assertSame([], $this->documentRepository->updates);
        static::assertSame([], $this->documentRepository->deletes);
    }

    public function testMatchesVariantDocumentsByParentFileAndTitle(): void
    {
        $this->documentRepository->addSearch(new ProductDocumentCollection());

        $this->controller->sync(
            self::PRODUCT_ID,
            $this->createRequest([['mediaId' => self::MEDIA_ID, 'title' => 'Manual']], dryRun: true),
            Context::createDefaultContext(),
        );

        static::assertEquals(
            [
                new EqualsFilter('product.parentId', self::PRODUCT_ID),
                new EqualsFilter('mediaId', self::MEDIA_ID),
                new EqualsFilter('title', 'Manual'),
            ],
            $this->documentRepository->searchedCriteria[0]->getFilters(),
        );
    }

    public function testAppliesOnlyTitleAndFileChangesToVariantDocuments(): void
    {
        $variantDocument = $this->createVariantDocument('variant-a');
        $this->documentRepository->addSearch(new ProductDocumentCollection([$variantDocument]));

        $this->controller->sync(
            self::PRODUCT_ID,
            $this->createRequest([[
                'mediaId' => self::MEDIA_ID,
                'title' => 'Manual',
                'changes' => ['title' => 'Guide', 'position' => 5],
            ]]),
            Context::createDefaultContext(),
        );

        static::assertSame(
            [['id' => $variantDocument->getId(), 'title' => 'Guide']],
            $this->documentRepository->getPayloads(StaticEntityRepository::UPDATE),
        );
        static::assertSame([], $this->documentRepository->deletes);
    }

    public function testRemovesVariantDocumentsInTheSystemLanguage(): void
    {
        $variantDocument = $this->createVariantDocument('variant-a');
        $this->documentRepository->addSearch(new ProductDocumentCollection([$variantDocument]));

        $this->controller->sync(
            self::PRODUCT_ID,
            $this->createRequest([['mediaId' => self::MEDIA_ID, 'title' => 'Manual']]),
            Context::createDefaultContext(),
        );

        static::assertSame(
            [['id' => $variantDocument->getId()]],
            $this->documentRepository->getPayloads(StaticEntityRepository::DELETE),
        );
        static::assertSame([], $this->documentRepository->updates);
    }

    public function testRemovesOnlyTheLanguageVersionInOtherLanguages(): void
    {
        $variantDocument = $this->createVariantDocument('variant-a', 'Handbuch');
        $this->documentRepository->addSearch(new ProductDocumentCollection([$variantDocument]));
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Uuid::randomHex(), Defaults::LANGUAGE_SYSTEM]);

        $this->controller->sync(
            self::PRODUCT_ID,
            $this->createRequest([['mediaId' => self::MEDIA_ID, 'title' => 'Handbuch']]),
            $context,
        );

        static::assertSame(
            [['id' => $variantDocument->getId(), 'title' => null, 'mediaId' => null]],
            $this->documentRepository->getPayloads(StaticEntityRepository::UPDATE),
        );
        static::assertSame([], $this->documentRepository->deletes);
    }

    public function testSkipsVariantDocumentsWhoseTitleOnlyMatchesIgnoringCase(): void
    {
        $this->documentRepository->addSearch(new ProductDocumentCollection([
            $this->createVariantDocument('variant-a', 'MANUAL'),
            $this->createVariantDocument('variant-b', 'Manual '),
        ]));

        $response = $this->controller->sync(
            self::PRODUCT_ID,
            $this->createRequest([['mediaId' => self::MEDIA_ID, 'title' => 'Manual']]),
            Context::createDefaultContext(),
        );

        static::assertSame(['variantIds' => []], json_decode((string) $response->getContent(), true));
        static::assertSame([], $this->documentRepository->deletes);
    }

    /**
     * @param array<mixed> $operations
     */
    #[DataProvider('invalidOperationsProvider')]
    public function testRejectsInvalidOperations(array $operations): void
    {
        $this->expectExceptionObject(ProductException::invalidDocumentVariantOperations());

        $this->controller->sync(self::PRODUCT_ID, $this->createRequest($operations), Context::createDefaultContext());
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidOperationsProvider(): iterable
    {
        yield 'not an object' => [['x']];
        yield 'missing media id' => [[['title' => 'Manual']]];
        yield 'media id is not a string' => [[['mediaId' => ['a'], 'title' => 'Manual']]];
        yield 'missing title' => [[['mediaId' => self::MEDIA_ID]]];
        yield 'title is not a string' => [[['mediaId' => self::MEDIA_ID, 'title' => ['Manual']]]];
        yield 'changes are not an object' => [[['mediaId' => self::MEDIA_ID, 'title' => 'Manual', 'changes' => 'x']]];
    }

    public function testSkipsWritesWhenNoVariantDocumentMatches(): void
    {
        $this->documentRepository->addSearch(new ProductDocumentCollection());

        $response = $this->controller->sync(
            self::PRODUCT_ID,
            $this->createRequest([['mediaId' => self::MEDIA_ID, 'title' => 'Manual']]),
            Context::createDefaultContext(),
        );

        static::assertSame(['variantIds' => []], json_decode((string) $response->getContent(), true));
        static::assertSame([], $this->documentRepository->updates);
        static::assertSame([], $this->documentRepository->deletes);
    }

    /**
     * @param array<mixed> $operations
     */
    private function createRequest(array $operations, bool $dryRun = false): Request
    {
        return new Request([], ['operations' => $operations, 'dryRun' => $dryRun]);
    }

    private function createVariantDocument(string $variantId, string $title = 'Manual'): ProductDocumentEntity
    {
        $document = new ProductDocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setUniqueIdentifier($document->getId());
        $document->setProductId($variantId);
        $document->setTitle($title);
        $document->setTranslated(['title' => $title]);

        return $document;
    }
}
