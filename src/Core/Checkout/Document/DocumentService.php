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
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Util\Random;

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

    /**
     * @throws InvalidDocumentGeneratorTypeException
     * @throws InvalidFileGeneratorTypeException
     */
    public function create(
        string $orderId,
        string $documentTypeId,
        string $fileType,
        DocumentConfiguration $config,
        Context $context
    ): string {
        $documentType = $this->getDocumentTypeById($documentTypeId, $context);

        if (!$documentTypeId || !$this->documentGeneratorRegistry->hasGenerator($documentType->getTechnicalName())) {
            throw new InvalidDocumentGeneratorTypeException($documentType ? $documentType->getTechnicalName() : $documentTypeId);
        }

        if (!$this->fileGeneratorRegistry->hasGenerator($fileType)) {
            throw new InvalidFileGeneratorTypeException($fileType);
        }

        // create version of order to ensure the document stays the same even if the order changes
        $orderVersionId = $this->orderRepository->createVersion($orderId, $context, self::VERSION_NAME);

        $documentId = Uuid::uuid4()->getHex();

        $documentConfiguration = $this->documentConfigurationService->getConfiguration(
            $context,
            $documentTypeId,
            $config->toArray()
        );

        $this->documentRepository->create([
            [
                'id' => $documentId,
                'documentTypeId' => $documentTypeId,
                'fileType' => $fileType,
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'config' => $documentConfiguration->toArray(),
                'deepLinkCode' => Random::getAlphanumericString(32),
            ],
        ],
            $context
        );

        return $documentId;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidDocumentGeneratorTypeException
     * @throws InvalidFileGeneratorTypeException
     * @throws DocumentGenerationException
     * @throws InvalidOrderException
     * @throws InvalidDocumentException
     */
    public function getDocumentByIdAndToken(string $documentId, string $token, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('id', $documentId),
            new EqualsFilter('deepLinkCode', $token),
        ]));
        $document = $this->documentRepository->search($criteria, $context)->get($documentId);

        if (!$document) {
            throw new InvalidDocumentException($documentId);
        }

        return $this->renderDocument($document, $context);
    }

    /**
     * @throws InvalidDocumentGeneratorTypeException
     * @throws InvalidOrderException
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidFileGeneratorTypeException
     * @throws DocumentGenerationException
     * @throws InvalidDocumentException
     */
    public function getDocumentByOrder(
        string $orderId,
        string $documentType,
        string $fileType,
        DocumentConfiguration $config,
        Context $context
    ): string {
        $documentId = $this->create($orderId, $documentType, $fileType, $config, $context);

        /** @var DocumentEntity|null $document */
        $document = $this->documentRepository->search(new Criteria([$documentId]), $context)->get($documentId);
        if (!$document) {
            throw new InvalidDocumentException($documentId);
        }

        return $this->renderDocument($document, $context);
    }

    public function renderDocument(DocumentEntity $document, Context $context): string
    {
        $documentGenerator = $this->documentGeneratorRegistry->getGenerator($document->getDocumentType()->getTechnicalName());
        $fileGenerator = $this->fileGeneratorRegistry->getGenerator($document->getFileType());

        $order = $this->getOrderById($document->getOrderId(), $document->getOrderVersionId(), $context);

        if (!$order) {
            throw new InvalidOrderException($document->getOrderId());
        }

        // todo create custom serializer
        $config = DocumentConfigurationFactory::createConfiguration($document->getConfig());

        $rendered = $documentGenerator->generateFromTemplate($order, $config, $context);

        // todo create struct for return type which includes filename? and extension!
        return $fileGenerator->generate($rendered);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws DocumentGenerationException
     */
    private function getOrderById(string $orderId, string $versionId, Context $context): ?OrderEntity
    {
        if ($versionId === Defaults::LIVE_VERSION) {
            throw new DocumentGenerationException('Only versioned orders can be used for document generation.');
        }

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions');

        return $this->orderRepository->search($criteria, $context->createWithVersionId($versionId))->get($orderId);
    }

    private function getDocumentTypeById(string $documentTypeId, Context $context): ?DocumentTypeEntity
    {
        return $this->documentTypeRepository->search(new Criteria([$documentTypeId]), $context)->get($documentTypeId);
    }
}
