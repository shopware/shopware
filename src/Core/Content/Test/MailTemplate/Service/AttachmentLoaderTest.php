<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\MailTemplate\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Content\MailTemplate\Service\AttachmentLoader;
use Shopware\Core\Content\MailTemplate\Service\Event\AttachmentLoaderCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class AttachmentLoaderTest extends TestCase
{
    private AttachmentLoader $attachmentLoader;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $documentRepositoryMock;

    /**
     * @var MockObject|DocumentGenerator
     */
    private $documentGeneratorMock;

    /**
     * @var MockObject|EventDispatcherInterface
     */
    private $eventDispatcherMock;

    protected function setUp(): void
    {
        $this->documentRepositoryMock = $this->createMock(EntityRepositoryInterface::class);
        $this->documentGeneratorMock = $this->createMock(DocumentGenerator::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->attachmentLoader = new AttachmentLoader(
            $this->documentRepositoryMock,
            $this->documentGeneratorMock,
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

        $generatedDocument = new RenderedDocument();
        $generatedDocument->setContent('foo');
        $generatedDocument->setName('bar.pdf');
        $generatedDocument->setContentType('application/pdf');
        $this->documentGeneratorMock->expects(static::once())->method('readDocument')->willReturn($generatedDocument);

        $attachments = $this->attachmentLoader->load([$document->getId()], Context::createDefaultContext());
        static::assertCount(1, $attachments);
        static::assertIsArray($attachments[0]);
        static::assertArrayHasKey('content', $attachments[0]);
        static::assertSame($generatedDocument->getContent(), $attachments[0]['content']);

        static::assertArrayHasKey('fileName', $attachments[0]);
        static::assertSame($generatedDocument->getName(), $attachments[0]['fileName']);

        static::assertArrayHasKey('mimeType', $attachments[0]);
        static::assertSame($generatedDocument->getContentType(), $attachments[0]['mimeType']);
    }
}
