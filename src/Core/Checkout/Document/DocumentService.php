<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorRegistry;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Checkout\Document\Exception\InvalidFileGeneratorTypeException;
use Shopware\Core\Checkout\Document\FileGenerator\FileGeneratorRegistry;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

class DocumentService
{
    public const VERSION_NAME = 'document';

    /**
     * @var DocumentGeneratorRegistry
     */
    protected $documentGeneratorRegistry;

    /**
     * @var FileGeneratorRegistry
     */
    protected $fileGeneratorRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $documentRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $documentTypeRepository;
    /**
     * @var DocumentConfigurationService
     */
    private $documentConfigurationService;

    public function __construct(
        DocumentGeneratorRegistry $documentGeneratorRegistry,
        FileGeneratorRegistry $fileGeneratorRegistry,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $documentRepository,
        EntityRepositoryInterface $documentTypeRepository,
        DocumentConfigurationService $documentConfigurationService
    ) {
        $this->documentGeneratorRegistry = $documentGeneratorRegistry;
        $this->fileGeneratorRegistry = $fileGeneratorRegistry;
        $this->orderRepository = $orderRepository;
        $this->documentRepository = $documentRepository;
        $this->documentTypeRepository = $documentTypeRepository;
        $this->documentConfigurationService = $documentConfigurationService;
    }

    public function create(
        string $orderId,
        string $documentTypeId,
        string $fileType,
        DocumentConfiguration $config,
        Context $context
    ): DocumentIdStruct {
        $documentType = $this->getDocumentTypeById($documentTypeId, $context);

        if (!$documentTypeId || !$documentType || !$this->documentGeneratorRegistry->hasGenerator($documentType->getTechnicalName())) {
            throw new InvalidDocumentGeneratorTypeException($documentType ? $documentType->getTechnicalName() : $documentTypeId);
        }

        if (!$this->fileGeneratorRegistry->hasGenerator($fileType)) {
            throw new InvalidFileGeneratorTypeException($fileType);
        }

        // create version of order to ensure the document stays the same even if the order changes
        $orderVersionId = $this->orderRepository->createVersion($orderId, $context, self::VERSION_NAME);

        $documentId = Uuid::randomHex();

        $documentConfiguration = $this->documentConfigurationService->getConfiguration(
            $context,
            $documentTypeId,
            $config->toArray()
        );

        $deepLinkCode = Random::getAlphanumericString(32);
        $this->documentRepository->create([
            [
                'id' => $documentId,
                'documentTypeId' => $documentTypeId,
                'fileType' => $fileType,
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'config' => $documentConfiguration->toArray(),
                'deepLinkCode' => $deepLinkCode,
            ],
        ],
            $context
        );

        return new DocumentIdStruct($documentId, $deepLinkCode);
    }

    public function getDocumentByIdAndToken(string $documentId, string $deepLinkCode, Context $context): DocumentEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('id', $documentId),
            new EqualsFilter('deepLinkCode', $deepLinkCode),
        ]));
        $document = $this->documentRepository->search($criteria, $context)->get($documentId);

        if (!$document) {
            throw new InvalidDocumentException($documentId);
        }

        return $document;
    }

    public function getDocumentByOrder(
        string $orderId,
        string $documentType,
        string $fileType,
        DocumentConfiguration $config,
        Context $context
    ): DocumentGenerated {
        $documentId = $this->create($orderId, $documentType, $fileType, $config, $context);

        /** @var DocumentEntity|null $document */
        $document = $this->documentRepository->search(new Criteria([$documentId]), $context)->get($documentId);
        if (!$document) {
            throw new InvalidDocumentException($documentId->getId());
        }

        return $this->renderDocument($document, $context);
    }

    public function renderDocument(DocumentEntity $document, Context $context): DocumentGenerated
    {
        $documentGenerator = $this->documentGeneratorRegistry->getGenerator($document->getDocumentType()->getTechnicalName());
        $fileGenerator = $this->fileGeneratorRegistry->getGenerator($document->getFileType());

        $this->validateVersion($document->getOrderVersionId());

        $order = $this->getOrderById($document->getOrderId(), $document->getOrderVersionId(), $context);

        if (!$order) {
            throw new InvalidOrderException($document->getOrderId());
        }

        $config = DocumentConfigurationFactory::createConfiguration($document->getConfig());

        $documentGenerated = new DocumentGenerated();

        $documentGenerated->setHtml($documentGenerator->generateFromTemplate($order, $config, $context));

        $documentGenerated->setFilename($documentGenerator->getFileName($config) . '.' . $fileGenerator->getExtension());

        $documentGenerated->setPageOrientation($config->getPageOrientation());

        $documentGenerated->setFileBlob($fileGenerator->generate($documentGenerated));

        return $documentGenerated;
    }

    public function getPreview(
        string $orderId,
        string $deepLinkCode,
        string $documentTypeId,
        string $fileType,
        DocumentConfiguration $config,
        Context $context
    ): DocumentGenerated {
        $documentType = $this->getDocumentTypeById($documentTypeId, $context);

        if (!$documentTypeId || !$documentType || !$this->documentGeneratorRegistry->hasGenerator($documentType->getTechnicalName())) {
            throw new InvalidDocumentGeneratorTypeException($documentType ? $documentType->getTechnicalName() : $documentTypeId);
        }
        $fileGenerator = $this->fileGeneratorRegistry->getGenerator($fileType);
        $documentGenerator = $this->documentGeneratorRegistry->getGenerator($documentType->getTechnicalName());

        $order = $this->getOrderByIdAndToken($orderId, $deepLinkCode, Defaults::LIVE_VERSION, $context);

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        $documentConfiguration = $this->documentConfigurationService->getConfiguration(
            $context,
            $documentTypeId,
            $config->toArray()
        );

        $documentGenerated = new DocumentGenerated();

        $documentGenerated->setHtml($documentGenerator->generateFromTemplate($order, $documentConfiguration, $context));

        $documentGenerated->setFilename($documentGenerator->getFileName($config) . '.' . $fileGenerator->getExtension());

        $documentGenerated->setPageOrientation($config->getPageOrientation());

        $documentGenerated->setFileBlob($fileGenerator->generate($documentGenerated));

        return $documentGenerated;
    }

    private function getOrderByIdAndToken(
        string $orderId,
        string $deepLinkCode,
        string $versionId,
        Context $context
    ): ?OrderEntity {
        $criteria = (new Criteria([$orderId]))
            ->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode))
            ->addAssociation('lineItems')
            ->addAssociation('transactions');

        return $this->orderRepository->search($criteria, $context->createWithVersionId($versionId))->get($orderId);
    }

    private function getOrderById(
        string $orderId,
        string $versionId,
        Context $context
    ): ?OrderEntity {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions');

        return $this->orderRepository->search($criteria, $context->createWithVersionId($versionId))->get($orderId);
    }

    private function getDocumentTypeById(string $documentTypeId, Context $context): ?DocumentTypeEntity
    {
        return $this->documentTypeRepository->search(new Criteria([$documentTypeId]), $context)->get($documentTypeId);
    }

    private function validateVersion(string $versionId): void
    {
        if ($versionId === Defaults::LIVE_VERSION) {
            throw new DocumentGenerationException('Only versioned orders can be used for document generation.');
        }
    }
}
