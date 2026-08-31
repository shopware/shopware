<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Shared\MailFlow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Content\Shared\MailFlow\DocumentResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentResolver::class)]
class DocumentResolverTest extends TestCase
{
    public function testDocumentTypeIdsResolveTheLatestDocumentPerTypeWithEveryFormat(): void
    {
        $orderId = Uuid::randomHex();
        $typeA = Uuid::randomHex();
        $typeB = Uuid::randomHex();
        $latestOfA = Uuid::randomHex();
        $latestOfB = Uuid::randomHex();
        $context = Context::createDefaultContext();

        // sorted ascending, so the last document of each type is the latest one
        $documents = new DocumentCollection([
            $this->createDocument(Uuid::randomHex(), $typeA),
            $this->createDocument($latestOfA, $typeA),
            $this->createDocument($latestOfB, $typeB),
        ]);

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria) use ($orderId, $typeA, $typeB): bool {
                    $filters = $criteria->getFilters();

                    return $filters[0] instanceof EqualsFilter
                        && $filters[0]->getValue() === $orderId
                        && $filters[1] instanceof EqualsAnyFilter
                        && $filters[1]->getValue() === [$typeA, $typeB]
                        && $criteria->getSorting()[0]->getDirection() === FieldSorting::ASCENDING;
                }),
                $context,
            )
            ->willReturn(new EntitySearchResult('document', 3, $documents, null, new Criteria(), $context));

        $resolved = $this->createResolver(documentRepository: $documentRepository)->resolve(
            ['documentTypeIds' => [$typeA, $typeB]],
            [],
            $orderId,
            $context,
        );

        static::assertSame([$latestOfA, $latestOfB], array_keys($resolved));
        static::assertSame([null, null], array_values($resolved));
    }

    public function testDocumentTypeIdsUnionPreResolvedIds(): void
    {
        $orderId = Uuid::randomHex();
        $preResolvedId = Uuid::randomHex();
        $latestId = Uuid::randomHex();

        $resolved = $this->createResolver(documentRepository: $this->createRepositoryReturningDocuments($latestId))->resolve(
            ['documentTypeIds' => [Uuid::randomHex()]],
            [$preResolvedId],
            $orderId,
            Context::createDefaultContext(),
        );

        static::assertSame([$preResolvedId, $latestId], array_keys($resolved));
    }

    #[DataProvider('emptyOrderIdProvider')]
    public function testConfiguredDocumentsAreIgnoredWithoutOrderId(?string $orderId): void
    {
        $preResolvedId = Uuid::randomHex();

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository->expects($this->never())->method('search');
        $documentRepository->expects($this->never())->method('searchIds');

        $resolver = $this->createResolver(documentRepository: $documentRepository);

        static::assertSame([$preResolvedId => null], $resolver->resolve(
            ['documentTypeIds' => [Uuid::randomHex()]],
            [$preResolvedId],
            $orderId,
            Context::createDefaultContext(),
        ));

        static::assertSame([$preResolvedId => null], $resolver->resolve(
            ['documentType' => 'invoice'],
            [$preResolvedId],
            $orderId,
            Context::createDefaultContext(),
        ));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function emptyOrderIdProvider(): iterable
    {
        yield 'null order id' => [null];
        yield 'empty order id' => [''];
    }

    public function testDocumentTypeResolvesTheLatestDocumentWithTheConfiguredFormats(): void
    {
        $orderId = Uuid::randomHex();
        $documentId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('searchIds')
            ->with(
                static::callback(static fn (Criteria $criteria): bool => $criteria->getLimit() === 1),
                $context,
            )
            ->willReturn(IdSearchResult::fromIds([$documentId], new Criteria(), $context));

        $resolved = $this->createResolver(documentRepository: $documentRepository)->resolve(
            ['documentType' => 'invoice', 'fileFormats' => ['pdf', 'html']],
            [],
            $orderId,
            $context,
        );

        static::assertSame([$documentId => ['pdf', 'html']], $resolved);
    }

    public function testEmptyFileFormatsMeanEveryGeneratedFormat(): void
    {
        $documentId = Uuid::randomHex();

        $resolved = $this->createResolver(documentRepository: $this->createRepositoryReturning($documentId))->resolve(
            ['documentType' => 'invoice', 'fileFormats' => []],
            [],
            Uuid::randomHex(),
            Context::createDefaultContext(),
        );

        static::assertSame([$documentId => null], $resolved);
    }

    public function testPreResolvedIdsAlwaysCoverEveryFormat(): void
    {
        $preResolvedId = Uuid::randomHex();
        $resolvedId = Uuid::randomHex();

        $resolved = $this->createResolver(documentRepository: $this->createRepositoryReturning($resolvedId))->resolve(
            ['documentType' => 'invoice', 'fileFormats' => ['pdf']],
            [$preResolvedId],
            Uuid::randomHex(),
            Context::createDefaultContext(),
        );

        static::assertSame([$preResolvedId => null, $resolvedId => ['pdf']], $resolved);
    }

    public function testAPreResolvedIdIsNotNarrowedByTheConfiguredFormats(): void
    {
        $documentId = Uuid::randomHex();

        $resolved = $this->createResolver(documentRepository: $this->createRepositoryReturning($documentId))->resolve(
            ['documentType' => 'invoice', 'fileFormats' => ['pdf']],
            [$documentId],
            Uuid::randomHex(),
            Context::createDefaultContext(),
        );

        static::assertSame([$documentId => null], $resolved);
    }

    public function testNoDocumentTypeResolvesOnlyPreResolvedIds(): void
    {
        $preResolvedId = Uuid::randomHex();

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository->expects($this->never())->method('searchIds');

        $resolved = $this->createResolver(documentRepository: $documentRepository)->resolve(
            ['documentType' => ''],
            [$preResolvedId],
            Uuid::randomHex(),
            Context::createDefaultContext(),
        );

        static::assertSame([$preResolvedId => null], $resolved);
    }

    public function testNoMatchingDocumentResolvesOnlyPreResolvedIds(): void
    {
        $preResolvedId = Uuid::randomHex();

        $resolved = $this->createResolver(documentRepository: $this->createRepositoryReturning(null))->resolve(
            ['documentType' => 'invoice'],
            [$preResolvedId],
            Uuid::randomHex(),
            Context::createDefaultContext(),
        );

        static::assertSame([$preResolvedId => null], $resolved);
    }

    /**
     * @param EntityRepository<DocumentCollection>|null $documentRepository
     */
    private function createResolver(?EntityRepository $documentRepository = null): DocumentResolver
    {
        return new DocumentResolver($documentRepository ?? static::createStub(EntityRepository::class));
    }

    private function createDocument(string $id, string $documentTypeId): DocumentEntity
    {
        $document = new DocumentEntity();
        $document->setId($id);
        $document->setDocumentTypeId($documentTypeId);

        return $document;
    }

    /**
     * @return EntityRepository<DocumentCollection>
     */
    private function createRepositoryReturningDocuments(string ...$documentIds): EntityRepository
    {
        $context = Context::createDefaultContext();

        // one document per type, so each one is the latest of its own type
        $documents = new DocumentCollection(array_map(
            fn (string $id): DocumentEntity => $this->createDocument($id, Uuid::randomHex()),
            $documentIds,
        ));

        $documentRepository = static::createStub(EntityRepository::class);
        $documentRepository->method('search')->willReturn(
            new EntitySearchResult('document', $documents->count(), $documents, null, new Criteria(), $context)
        );

        return $documentRepository;
    }

    /**
     * @return EntityRepository<DocumentCollection>
     */
    private function createRepositoryReturning(?string $documentId): EntityRepository
    {
        $context = Context::createDefaultContext();

        $documentRepository = static::createStub(EntityRepository::class);
        $documentRepository->method('searchIds')->willReturn(
            IdSearchResult::fromIds($documentId === null ? [] : [$documentId], new Criteria(), $context)
        );

        return $documentRepository;
    }
}
