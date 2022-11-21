<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorInterface;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorRegistry;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\Event\DocumentOrderCriteriaEvent;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Exception\DocumentNumberAlreadyExistsException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Checkout\Document\Exception\InvalidFileGeneratorTypeException;
use Shopware\Core\Checkout\Document\FileGenerator\FileGeneratorInterface;
use Shopware\Core\Checkout\Document\FileGenerator\FileGeneratorRegistry;
use Shopware\Core\Checkout\Document\FileGenerator\PdfGenerator;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.5.0 - Will be removed, use DocumentGenerator instead
 */
class DocumentService
{
    public const VERSION_NAME = 'document';

    /**
     * @internal (flag:FEATURE_NEXT_15053) should be removed again
     */
    public const GENERATING_PDF_STATE = 'generating-pdf';

    /**
     * @var DocumentGeneratorRegistry
     */
    protected $documentGeneratorRegistry;

    /**
     * @var FileGeneratorRegistry
     */
    protected $fileGeneratorRegistry;

    /**
     * @var EntityRepository
     */
    protected $orderRepository;

    /**
     * @var EntityRepository
     */
    protected $documentRepository;

    /**
     * @var EntityRepository
     */
    private $documentTypeRepository;

    /**
     * @var EntityRepository
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

    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(
        Connection $connection,
        DocumentGeneratorRegistry $documentGeneratorRegistry,
        FileGeneratorRegistry $fileGeneratorRegistry,
        EntityRepository $orderRepository,
        EntityRepository $documentRepository,
        EntityRepository $documentTypeRepository,
        EntityRepository $documentConfigRepository,
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
        $this->connection = $connection;
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $documentType = $this->getDocumentTypeByName($documentTypeName, $context);

        if ($documentType === null || !$this->documentGeneratorRegistry->hasGenerator($documentTypeName)) {
            throw new InvalidDocumentGeneratorTypeException($documentTypeName);
        }

        if (!$this->fileGeneratorRegistry->hasGenerator($fileType)) {
            throw new InvalidFileGeneratorTypeException($fileType);
        }

        $documentConfiguration = $this->getConfiguration(
            $context,
            $documentType->getId(),
            $orderId,
            $config->jsonSerialize()
        );

        if ($documentConfiguration->getDocumentNumber()) {
            $this->checkDocumentNumberAlreadyExits($documentTypeName, $documentConfiguration->getDocumentNumber(), $context);
        }

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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $config = DocumentConfigurationFactory::createConfiguration($document->getConfig());

        $generatedDocument = new GeneratedDocument();
        if (!$this->hasValidFile($document) && !$document->isStatic()) {
            $generatedDocument->setPageOrientation($config->getPageOrientation());
            $generatedDocument->setPageSize($config->getPageSize());

            $fileGenerator = $this->fileGeneratorRegistry->getGenerator($document->getFileType());
            $generatedDocument->setContentType($fileGenerator->getContentType());
            $this->generateDocument($document, $context, $generatedDocument, $config, $fileGenerator);
        } else {
            if (
                !$this->hasValidFile($document)
                || $document->getDocumentMediaFile() === null
                || $document->getDocumentMediaFile()->getMimeType() === null
                || $document->getDocumentMediaFileId() === null
            ) {
                throw new DocumentGenerationException('Document is missing file data');
            }

            /** @var MediaEntity $documentMediaFile */
            $documentMediaFile = $document->getDocumentMediaFile();
            $generatedDocument->setFilename($documentMediaFile->getFileName() . '.' . $documentMediaFile->getFileExtension());
            $generatedDocument->setContentType($documentMediaFile->getMimeType() ?? PdfGenerator::FILE_CONTENT_TYPE);

            $fileBlob = '';
            $mediaService = $this->mediaService;
            $context->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($mediaService, $documentMediaFile, &$fileBlob): void {
                $fileBlob = $mediaService->loadFile($documentMediaFile->getId(), $context);
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $documentType = $this->getDocumentTypeByName($documentTypeName, $context);

        if ($documentType === null || !$this->documentGeneratorRegistry->hasGenerator($documentTypeName)) {
            throw new InvalidDocumentGeneratorTypeException($documentTypeName);
        }
        $fileGenerator = $this->fileGeneratorRegistry->getGenerator($fileType);
        $documentGenerator = $this->documentGeneratorRegistry->getGenerator($documentTypeName);

        $orderVersionId = Defaults::LIVE_VERSION;

        if (!empty($config->jsonSerialize()['custom']['invoiceNumber'])) {
            $invoiceNumber = (string) $config->jsonSerialize()['custom']['invoiceNumber'];
            $orderVersionId = $this->getInvoiceOrderVersionId($orderId, $invoiceNumber);
        }

        $order = $this->getOrderById($orderId, $orderVersionId, $context, $deepLinkCode);

        $documentConfiguration = $this->getConfiguration(
            $context,
            $documentType->getId(),
            $orderId,
            $config->jsonSerialize()
        );

        if (!Feature::isActive('FEATURE_NEXT_15053')) {
            $context->addState(self::GENERATING_PDF_STATE);
        }
        $generatedDocument = new GeneratedDocument();
        $generatedDocument->setHtml($documentGenerator->generate($order, $documentConfiguration, $context));
        $generatedDocument->setFilename(
            $documentGenerator->getFileName($config) . '.' . $fileGenerator->getExtension()
        );
        $generatedDocument->setPageOrientation($config->getPageOrientation() ?? $documentConfiguration->getPageOrientation());
        $generatedDocument->setPageSize($config->getPageSize());
        $generatedDocument->setFileBlob($fileGenerator->generate($generatedDocument));
        $generatedDocument->setContentType($fileGenerator->getContentType());

        return $generatedDocument;
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        /** @var DocumentEntity $document */
        $document = $this->documentRepository->search(new Criteria([$documentId]), $context)->first();

        if ($document->getDocumentMediaFile() !== null) {
            throw new DocumentGenerationException('Document already exists');
        }

        if ($document->isStatic() === false) {
            throw new DocumentGenerationException('This document is dynamically generated and cannot be overwritten');
        }

        $mediaFile = $this->mediaService->fetchFile($uploadedFileRequest);

        $fileName = (string) $uploadedFileRequest->query->get('fileName');
        if ($fileName === '') {
            throw new DocumentGenerationException('Parameter "fileName" is missing');
        }

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
                    'orderVersionId' => $document->getOrderVersionId(),
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
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('currency')
            ->addAssociation('language.locale')
            ->addAssociation('addresses.country')
            ->addAssociation('addresses.salutation')
            ->addAssociation('addresses.countryState')
            ->addAssociation('deliveries.positions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState')
            ->addAssociation('orderCustomer.customer')
            ->addAssociation('orderCustomer.salutation');

        $criteria->getAssociation('lineItems')->addSorting(new FieldSorting('position'));
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));
        $criteria->getAssociation('deliveries')->addSorting(new FieldSorting('createdAt'));

        if ($deepLinkCode !== '') {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode));
        }

        $versionContext = $context->createWithVersionId($versionId)->assign([
            'languageIdChain' => array_unique(array_filter([$this->getOrderLanguageId($orderId), $context->getLanguageId()])),
        ]);

        $this->eventDispatcher->dispatch(new DocumentOrderCriteriaEvent($criteria, $versionContext));

        /** @var ?OrderEntity $order */
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

    private function validateVersion(DocumentEntity $document, Context $context): void
    {
        if ($document->getOrderVersionId() === Defaults::LIVE_VERSION) {
            // Only versioned orders can be used for document generation.
            $orderVersionId = $this->orderRepository->createVersion($document->getOrderId(), $context);
            $document->setOrderVersionId($orderVersionId);
            $this->documentRepository->update(
                [
                    [
                        'id' => $document->getId(),
                        'orderVersionId' => $orderVersionId,
                    ],
                ],
                $context
            );
        }
    }

    /**
     * @param array<string, int|string>|null $specificConfiguration
     */
    private function getConfiguration(
        Context $context,
        string $documentTypeId,
        string $orderId,
        ?array $specificConfiguration
    ): DocumentConfiguration {
        $specificConfiguration = $specificConfiguration ?? [];
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentTypeId', $documentTypeId));
        $criteria->addAssociation('logo');
        $criteria->addFilter(new EqualsFilter('global', true));

        /** @var DocumentBaseConfigEntity $globalConfig */
        $globalConfig = $this->documentConfigRepository->search($criteria, $context)->first();

        $order = $this->getOrderById($orderId, Defaults::LIVE_VERSION, $context);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentTypeId', $documentTypeId));
        $criteria->addAssociation('logo');
        $criteria->addFilter(new EqualsFilter('salesChannels.salesChannelId', $order->getSalesChannelId()));
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
        if (!\is_string($referencedDocumentType)) {
            throw new \RuntimeException('referencedDocumentType should be a string');
        }

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
                            'orderVersionId' => $document->getOrderVersionId(),
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
        DocumentEntity $document,
        Context $context,
        GeneratedDocument $generatedDocument,
        DocumentConfiguration $config,
        FileGeneratorInterface $fileGenerator
    ): void {
        if ($document->getDocumentType() === null) {
            throw new DocumentGenerationException('DocumentType missing');
        }

        $documentGenerator = $this->documentGeneratorRegistry->getGenerator(
            $document->getDocumentType()->getTechnicalName()
        );
        $this->validateVersion($document, $context);

        $order = $this->getOrderById($document->getOrderId(), $document->getOrderVersionId(), $context);

        if (!Feature::isActive('FEATURE_NEXT_15053')) {
            $context->addState(self::GENERATING_PDF_STATE);
        }
        $generatedDocument->setHtml($documentGenerator->generate($order, $config, $context));
        $generatedDocument->setFilename($documentGenerator->getFileName($config) . '.' . $fileGenerator->getExtension());
        $fileBlob = $fileGenerator->generate($generatedDocument);
        $generatedDocument->setFileBlob($fileBlob);

        $this->saveDocumentFile($document, $context, $fileBlob, $fileGenerator, $documentGenerator, $config);
    }

    private function checkDocumentNumberAlreadyExits(string $documentTypeName, ?string $documentNumber, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('documentType');
        $criteria->addFilter(new EqualsFilter('documentType.technicalName', $documentTypeName));
        $criteria->addFilter(new EqualsFilter('config.documentNumber', $documentNumber));

        $result = $this->documentRepository->searchIds($criteria, $context);

        if ($result->getTotal() !== 0) {
            throw new DocumentNumberAlreadyExistsException($documentNumber);
        }
    }

    private function getOrderLanguageId(string $orderId): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT LOWER(HEX(language_id)) FROM `order` WHERE `id` = :orderId',
            ['orderId' => Uuid::fromHexToBytes($orderId)],
        );
    }

    private function getInvoiceOrderVersionId(string $orderId, string $invoiceNumber): string
    {
        $orderVersionId = $this->connection->fetchOne('
            SELECT LOWER(HEX(order_version_id))
            FROM document INNER JOIN document_type
                ON document.document_type_id = document_type.id
            WHERE document_type.technical_name = :technicalName
            AND JSON_UNQUOTE(JSON_EXTRACT(document.config, "$.documentNumber")) = :invoiceNumber
            AND document.order_id = :orderId
        ', [
            'technicalName' => InvoiceGenerator::INVOICE,
            'invoiceNumber' => $invoiceNumber,
            'orderId' => Uuid::fromHexToBytes($orderId),
        ]);

        return $orderVersionId ?: Defaults::LIVE_VERSION;
    }
}
