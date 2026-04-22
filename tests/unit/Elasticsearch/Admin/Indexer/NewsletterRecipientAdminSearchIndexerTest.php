<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\Indexer\NewsletterRecipientAdminSearchIndexer;

/**
 * @internal
 */
#[CoversClass(NewsletterRecipientAdminSearchIndexer::class)]
class NewsletterRecipientAdminSearchIndexerTest extends TestCase
{
    private NewsletterRecipientAdminSearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->searchIndexer = new NewsletterRecipientAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            100
        );
    }

    public function testGetUpdatedIds(): void
    {
        $indexer = new NewsletterRecipientAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            100
        );

        $id = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('newsletter_recipient', [
                    new EntityWriteResult($id, ['email' => 'e@example.com'], 'newsletter_recipient', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([$id], $indexer->getUpdatedIds($event));
    }

    public function testGetEntity(): void
    {
        static::assertSame(NewsletterRecipientDefinition::ENTITY_NAME, $this->searchIndexer->getEntity());
    }

    public function testGetName(): void
    {
        static::assertSame('newsletter-recipient-listing', $this->searchIndexer->getName());
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->searchIndexer->getDecorated();
    }

    public function testGlobalData(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createMock(EntityRepository::class);
        $newsletterRecipient = new NewsletterRecipientEntity();
        $newsletterRecipient->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                'newsletter_recipient',
                1,
                new EntityCollection([$newsletterRecipient]),
                null,
                new Criteria(),
                $context
            )
        );

        $indexer = new NewsletterRecipientAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $repository,
            100
        );

        $result = [
            'total' => 1,
            'hits' => [
                ['id' => '809c1844f4734243b6aa04aba860cd45'],
            ],
        ];

        $data = $indexer->globalData($result, $context);

        static::assertSame($result['total'], $data['total']);
    }

    public function testFetching(): void
    {
        $connection = $this->getConnection();

        $indexer = new NewsletterRecipientAdminSearchIndexer(
            $connection,
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            100
        );

        $id = '809c1844f4734243b6aa04aba860cd45';
        $documents = $indexer->fetch([$id]);

        static::assertArrayHasKey($id, $documents);

        /** @var array<string, mixed> $document */
        $document = $documents[$id];

        static::assertSame($id, $document['id']);
        static::assertSame('newsletter@example.com john doe da nang 50000 main street tag 809c1844f4734243b6aa04aba860cd45', $document['text']);
        static::assertSame('newsletter@example.com', $document['email']);
        static::assertSame('John', $document['firstName']);
        static::assertSame('Doe', $document['lastName']);
        static::assertSame('optIn', $document['status']);
        static::assertSame('Da Nang', $document['city']);
        static::assertSame('Main Street', $document['street']);
        static::assertIsArray($document['tags']);
    }

    private function getConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    'email' => 'newsletter@example.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'status' => 'optIn',
                    'city' => 'Da Nang',
                    'zipCode' => '50000',
                    'street' => 'Main Street',
                    'tags' => 'Tag',
                    'tagIds' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6',
                    'salesChannelId' => 'aabbccdd11223344556677889900aabb',
                    'languageId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    'createdAt' => '2024-01-01 00:00:00.000',
                    'updatedAt' => null,
                ],
            ],
        );

        return $connection;
    }
}
