<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorInterface;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorRegistry;
use Shopware\Core\Checkout\Document\Event\DocumentOrderCriteriaEvent;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Checkout\Document\Exception\InvalidFileGeneratorTypeException;
use Shopware\Core\Checkout\Document\FileGenerator\FileGeneratorInterface;
use Shopware\Core\Checkout\Document\FileGenerator\FileGeneratorRegistry;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

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
     * @var EntityRepositoryInterface
     */
    private $documentConfigRepository;

    /**
     * @var MediaService
     */
    private $mediaService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        DocumentGeneratorRegistry $documentGeneratorRegistry,
        FileGeneratorRegistry $fileGeneratorRegistry,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $documentRepository,
        EntityRepositoryInterface $documentTypeRepository,
        EntityRepositoryInterface $documentConfigRepository,
        MediaService $mediaService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->documentGeneratorRegistry = $documentGeneratorRegistry;
        $this->fileGeneratorRegistry = $fileGeneratorRegistry;
        $this->orderRepository = $orderRepository;
        $this->documentRepository = $documentRepository;
        $this->documentTypeRepository = $documentTypeRepository;
        $this->documentConfigRepository = $documentConfigRepository;
        $this->mediaService = $mediaService;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws DocumentGenerationException
     * @throws InvalidDocumentGeneratorTypeException
     * @throws InvalidFileGeneratorTypeException
     */
    public function create(
        string $orderId,
        string $documentTypeName,
        string $fileType,
        DocumentConfiguration $config,
        Context $context,
        ?string $referencedDocumentId = null,
        bool $static = false
    ): DocumentIdStruct {
        $documentType = $this->getDocumentTypeByName($documentTypeName, $context);

        if ($documentType === null || !$this->documentGeneratorRegistry->hasGenerator($documentTypeName)) {
            throw new InvalidDocumentGeneratorTypeException($documentTypeName);
        }

        if (!$this->fileGeneratorRegistry->hasGenerator($fileType)) {
            throw new InvalidFileGeneratorTypeException($fileType);
        }

        $order = $this->getOrderById($orderId, Defaults::LIVE_VERSION, $context);

        $documentConfiguration = $this->getConfiguration(
            $context,
            $documentType->getId(),
            $order->getSalesChannelId(),
            $config->jsonSerialize()
        );

        if (property_exists($documentConfiguration, 'referencedDocumentType')) {
            if ($referencedDocumentId === null) {
                throw new DocumentGenerationException(
                    'referencedDocumentId must not be null for documents of type ' . $documentTypeName
                );
            }

            // if this document references a another document, retrive the version Id of its order
            $orderVersionId = $this->getVersionIdFromReferencedDocument(
                $referencedDocumentId,
                $orderId,
                $documentConfiguration,
                $context
            );
        } else {
            // create version of order to ensure the document stays the same even if the order changes
            $orderVersionId = $this->orderRepository->createVersion($orderId, $context, self::VERSION_NAME);
        }

        $documentId = Uuid::randomHex();
        $deepLinkCode = Random::getAlphanumericString(32);
        $this->documentRepository->create(
            [
                [
                    'id' => $documentId,
                    'documentTypeId' => $documentType->getId(),
                    'fileType' => $fileType,
                    'orderId' => $orderId,
                    'orderVersionId' => $orderVersionId,
                    'config' => $documentConfiguration->jsonSerialize(),
                    'static' => $static,
                    'deepLinkCode' => $deepLinkCode,
                    'referencedDocumentId' => $referencedDocumentId,
                ],
            ],
            $context
        );

        return new DocumentIdStruct($documentId, $deepLinkCode);
    }

    public function getDocument(DocumentEntity $document, Context $context): GeneratedDocument
    {
        $generatedDocument = new GeneratedDocument();
        if (!$this->hasValidFile($document) && !$document->isStatic()) {
            $this->validateVersion($document->getOrderVersionId());

            $documentGenerator = $this->documentGeneratorRegistry->getGenerator(
                $document->getDocumentType()->getTechnicalName()
            );
            $fileGenerator = $this->fileGeneratorRegistry->getGenerator($document->getFileType());

            $config = DocumentConfigurationFactory::createConfiguration($document->getConfig());
            $config->order = $this->getOrderById($document->getOrderId(), $document->getOrderVersionId(), $context);

            $generatedDocument = $this->generateDocument($documentGenerator, $fileGenerator, $config, $context);

            $this->saveDocumentFile(
                $document,
                $context,
                $generatedDocument->getFileBlob(),
                $fileGenerator,
                $documentGenerator,
                $config
            );
        } else {
            $generatedDocument->setFilename($document->getDocumentMediaFile()->getFileName() . '.' . $document->getDocumentMediaFile()->getFileExtension());
            $generatedDocument->setContentType($document->getDocumentMediaFile()->getMimeType());

            $fileBlob = '';
            $mediaService = $this->mediaService;
            $context->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($mediaService, $document, &$fileBlob): void {
                $fileBlob = $mediaService->loadFile($document->getDocumentMediaFileId(), $context);
            });
            $generatedDocument->setFileBlob($fileBlob);
        }

        return $generatedDocument;
    }

    /**
     * @throws InvalidDocumentGeneratorTypeException
     */
    public function preview(
        string $orderId,
        string $deepLinkCode,
        string $documentTypeName,
        string $fileType,
        DocumentConfiguration $config,
        Context $context
    ): GeneratedDocument {
        $documentType = $this->getDocumentTypeByName($documentTypeName, $context);

        if ($documentType === null || !$this->documentGeneratorRegistry->hasGenerator($documentTypeName)) {
            throw new InvalidDocumentGeneratorTypeException($documentTypeName);
        }
        $fileGenerator = $this->fileGeneratorRegistry->getGenerator($fileType);
        $documentGenerator = $this->documentGeneratorRegistry->getGenerator($documentType->getTechnicalName());

        $order = $this->getOrderById($orderId, Defaults::LIVE_VERSION, $context, $deepLinkCode);

        $documentConfiguration = $this->getConfiguration(
            $context,
            $documentType->getId(),
            $order->getSalesChannelId(),
            $config->jsonSerialize()
        );
        $documentConfiguration->order = $order;

        return $this->generateDocument($documentGenerator, $fileGenerator, $documentConfiguration, $context);
    }

    /**
     * @throws DocumentGenerationException
     * @throws InconsistentCriteriaIdsException
     */
    public function uploadFileForDocument(
        string $documentId,
        Context $context,
        Request $uploadedFileRequest
    ): DocumentIdStruct {
        /** @var DocumentEntity $document */
        $document = $this->documentRepository->search(new Criteria([$documentId]), $context)->first();

        if ($document->getDocumentMediaFile() !== null) {
            throw new DocumentGenerationException('Document already exists');
        }

        if ($document->isStatic() === false) {
            throw new DocumentGenerationException('This document is dynamically generated and cannot be overwritten');
        }

        $mediaFile = $this->mediaService->fetchFile($uploadedFileRequest);

        $fileName = $uploadedFileRequest->query->get('fileName');

        $mediaService = $this->mediaService;
        $mediaId = null;
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use (
            $fileName,
            $mediaService,
            $mediaFile,
            &$mediaId
        ): void {
            $mediaId = $mediaService->saveMediaFile($mediaFile, $fileName, $context, 'document');
        });

        $document->setDocumentMediaFileId($mediaId);
        $this->documentRepository->update(
            [
                [
                    'id' => $document->getId(),
                    'documentMediaFileId' => $document->getDocumentMediaFileId(),
                ],
            ],
            $context
        );

        return new DocumentIdStruct($documentId, $document->getDeepLinkCode());
    }

    private function hasValidFile(DocumentEntity $document): bool
    {
        return $document->getDocumentMediaFile() !== null && $document->getDocumentMediaFile()->getFileName() !== null;
    }

    private function getOrderById(
        string $orderId,
        string $versionId,
        Context $context,
        string $deepLinkCode = ''
    ): OrderEntity {
        $criteria = $this->getOrderBaseCriteria($orderId);

        if ($deepLinkCode !== '') {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode));
        }

        $versionContext = $context->createWithVersionId($versionId);

        $this->eventDispatcher->dispatch(new DocumentOrderCriteriaEvent($criteria, $versionContext));

        $order = $this->orderRepository->search($criteria, $versionContext)->get($orderId);

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        return $order;
    }

    private function getDocumentTypeByName(string $documentType, Context $context): ?DocumentTypeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $documentType));

        return $this->documentTypeRepository->search($criteria, $context)->first();
    }

    /**
     * @throws DocumentGenerationException
     */
    private function validateVersion(string $versionId): void
    {
        if ($versionId === Defaults::LIVE_VERSION) {
            throw new DocumentGenerationException('Only versioned orders can be used for document generation.');
        }
    }

    private function getConfiguration(
        Context $context,
        string $documentTypeId,
        string $salesChannelId,
        ?array $specificConfiguration
    ): DocumentConfiguration {
        $specificConfiguration = $specificConfiguration ?? [];
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentTypeId', $documentTypeId));
        $criteria->addAssociation('logo');
        $criteria->addFilter(new EqualsFilter('global', true));

        /** @var DocumentBaseConfigEntity $globalConfig */
        $globalConfig = $this->documentConfigRepository->search($criteria, $context)->first();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentTypeId', $documentTypeId));
        $criteria->addAssociation('logo');
        $criteria->addFilter(new EqualsFilter('salesChannels.salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('salesChannels.documentTypeId', $documentTypeId));

        $salesChannelConfig = $this->documentConfigRepository->search($criteria, $context)->first();

        return DocumentConfigurationFactory::createConfiguration($specificConfiguration, $globalConfig, $salesChannelConfig);
    }

    /**
     * @throws DocumentGenerationException
     */
    private function getVersionIdFromReferencedDocument(
        string $referencedDocumentId,
        string $orderId,
        DocumentConfiguration $documentConfiguration,
        Context $context
    ): string {
        $referencedDocumentType = $documentConfiguration->__get('referencedDocumentType');

        $criteria = (new Criteria([$referencedDocumentId]))
            ->addFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('document.documentType.technicalName', $referencedDocumentType),
                        new EqualsFilter('orderId', $orderId),
                    ]
                )
            )
            ->setLimit(1);

        /** @var DocumentEntity|null $referencedDocument */
        $referencedDocument = $this->documentRepository->search($criteria, $context)->get($referencedDocumentId);

        if (!$referencedDocument) {
            throw new DocumentGenerationException(
                sprintf(
                    'The given referenced document with id %s with type %s for order %s could not be found',
                    $referencedDocumentId,
                    $referencedDocumentType,
                    $orderId
                )
            );
        }

        return $referencedDocument->getOrderVersionId();
    }

    private function saveDocumentFile(
        DocumentEntity $document,
        Context $context,
        string $fileBlob,
        FileGeneratorInterface $fileGenerator,
        DocumentGeneratorInterface $documentGenerator,
        DocumentConfiguration $config
    ): void {
        try {
            $mediaService = $this->mediaService;
            $mediaId = null;
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use (
                $mediaService,
                $fileBlob,
                $fileGenerator,
                $documentGenerator,
                $document,
                $config,
                &$mediaId
            ): void {
                $mediaId = $mediaService->saveFile(
                    $fileBlob,
                    $fileGenerator->getExtension(),
                    $fileGenerator->getContentType(),
                    $documentGenerator->getFileName($config),
                    $context,
                    'document',
                    $document->getDocumentMediaFileId()
                );
            });

            if ($document->getDocumentMediaFileId() === null) {
                $document->setDocumentMediaFileId($mediaId);
                $this->documentRepository->update(
                    [
                        [
                            'id' => $document->getId(),
                            'documentMediaFileId' => $document->getDocumentMediaFileId(),
                        ],
                    ],
                    $context
                );
            }
        } catch (\Exception $e) {
            $document->setDocumentMediaFileId(null);
        }
    }

    private function generateDocument(
        DocumentGeneratorInterface $documentGenerator,
        FileGeneratorInterface $fileGenerator,
        DocumentConfiguration $config,
        Context $context
    ): GeneratedDocument {
        $generatedDocument = new GeneratedDocument();
        $generatedDocument->setPageOrientation($config->getPageOrientation());
        $generatedDocument->setPageSize($config->getPageSize());
        $generatedDocument->setHtml($documentGenerator->generate($config, $context));
        $generatedDocument->setFilename($documentGenerator->getFileName($config) . '.' . $fileGenerator->getExtension());
        $generatedDocument->setFileBlob($fileGenerator->generate($generatedDocument));
        $generatedDocument->setContentType($fileGenerator->getContentType());

        return $generatedDocument;
    }

    private function getOrderBaseCriteria(string $orderId): Criteria
    {
        return (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('currency')
            ->addAssociation('language.locale')
            ->addAssociation('addresses.country')
            ->addAssociation('deliveries.positions')
            ->addAssociation('deliveries.shippingMethod');
    }
}
