<?php declare(strict_types=1);

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Core\Content\MailTemplate\Service\AttachmentLoader;
use Shopware\Core\Content\MailTemplate\Service\Event\AttachmentLoaderCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AttachmentLoaderTest extends TestCase
{
    private AttachmentLoader $attachmentLoader;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $documentRepositoryMock;

    /**
     * @var MockObject|DocumentService
     */
    private $documentServiceMock;

    /**
     * @var MockObject|EventDispatcherInterface
     */
    private $eventDispatcherMock;

    protected function setUp(): void
    {
        $this->documentRepositoryMock = $this->createMock(EntityRepositoryInterface::class);
        $this->documentServiceMock = $this->createMock(DocumentService::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->attachmentLoader = new AttachmentLoader(
            $this->documentRepositoryMock,
            $this->documentServiceMock,
            $this->eventDispatcherMock
        );
    }

    public function testLoad(): void
    {
        $this->eventDispatcherMock->expects(static::once())->method('dispatch')->with(static::callback(static function (AttachmentLoaderCriteriaEvent $event) {
            $criteria = $event->getCriteria();

            return $criteria->hasAssociation('documentMediaFile') && $criteria->hasAssociation('documentType');
        }));

        $document = new DocumentEntity();
        $document->setId(Uuid::randomHex());
        $documentCollection = new DocumentCollection([$document]);
        $searchResult = new EntitySearchResult(
            'document',
            1,
            $documentCollection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->documentRepositoryMock->expects(static::once())->method('search')->willReturn($searchResult);

        $generatedDocument = new GeneratedDocument();
        $generatedDocument->setFileBlob('foo');
        $generatedDocument->setFilename('bar.pdf');
        $generatedDocument->setContentType('pdf');
        $this->documentServiceMock->expects(static::once())->method('getDocument')->willReturn($generatedDocument);

        $attachments = $this->attachmentLoader->load([$document->getId()], Context::createDefaultContext());
        static::assertCount(1, $attachments);
        static::assertIsArray($attachments[0]);
        static::assertArrayHasKey('content', $attachments[0]);
        static::assertSame($generatedDocument->getFileBlob(), $attachments[0]['content']);

        static::assertArrayHasKey('fileName', $attachments[0]);
        static::assertSame($generatedDocument->getFilename(), $attachments[0]['fileName']);

        static::assertArrayHasKey('mimeType', $attachments[0]);
        static::assertSame($generatedDocument->getContentType(), $attachments[0]['mimeType']);
    }
}
