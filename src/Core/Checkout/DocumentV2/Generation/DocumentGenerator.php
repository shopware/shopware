<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Checkout\DocumentV2\Provider\OrderVersionStrategy;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\ReferencedDocument;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentGenerator
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private DocumentDataProviderRegistry $documentDataProviderRegistry,
        private DocumentRendererRegistry $documentRendererRegistry,
        private DocumentNumberGenerator $documentNumberGenerator,
        private DocumentPersister $documentPersister,
        private DocumentDependencyResolver $dependencyResolver,
        private ReferencedDocumentResolver $referencedDocumentResolver,
        private EntityRepository $orderRepository,
    ) {
    }

    /**
     * Generates one logical document with one or more persisted document_file artifacts.
     *
     * The request must contain at least one format and a non-live order version id.
     *
     * For example, if the caller requests only `pdf` and the PDF renderer depends on `html`,
     * both formats are rendered, but only the PDF result is persisted as a document_file.
     *
     * @throws DocumentV2Exception
     */
    public function generate(DocumentGenerationRequest $generationRequest, Context $apiContext): DocumentEntity
    {
        [
            'generationRequest' => $generationRequest,
            'renderInput' => $renderInput,
            'renderState' => $renderState,
            'requestedFormats' => $requestedFormats,
            'resolvedReference' => $resolvedReference,
        ] = $this->generateDocument($generationRequest, $apiContext);

        return $this->documentPersister->persist(
            $generationRequest,
            $renderInput,
            $renderState,
            $requestedFormats,
            $resolvedReference,
            $apiContext,
        );
    }

    /**
     * Generates one logical document and returns the first requested format as a RenderedDocument.
     *
     * @throws DocumentV2Exception
     */
    public function preview(
        DocumentGenerationRequest $generationRequest,
        Context $apiContext,
    ): RenderedDocument {
        [
            'renderState' => $renderState,
            'requestedFormats' => $requestedFormats,
        ] = $this->generateDocument($generationRequest, $apiContext, true);

        $result = $renderState->require($requestedFormats[0]);

        $document = new RenderedDocument(
            name: $result->fileName . '.' . $result->fileExtension,
            fileExtension: $result->fileExtension,
            contentType: $result->mimeType,
        );
        $document->setContent($result->content);

        return $document;
    }

    /**
     * @throws DocumentV2Exception
     *
     * @return array{
     *     generationRequest: DocumentGenerationRequest,
     *     renderInput: RenderInput,
     *     renderState: RenderState,
     *     requestedFormats: list<string>,
     *     resolvedReference: ?ReferencedDocument
     * }
     */
    private function generateDocument(
        DocumentGenerationRequest $generationRequest,
        Context $apiContext,
        bool $preview = false,
    ): array {
        $this->validateGenerationRequest($generationRequest);

        $requestedFormats = $this->normalizeRequestedFormats($generationRequest->requestedFormats);

        $renderPlan = $this->dependencyResolver->resolve(
            $generationRequest->documentType,
            $requestedFormats,
        );

        $providers = $this->documentDataProviderRegistry->getByDocumentType(
            $generationRequest->documentType,
        );

        $strategy = $this->resolveOrderVersionStrategy($providers, $generationRequest->documentType);

        $criteria = new Criteria([$generationRequest->orderId]);

        foreach ($providers as $provider) {
            $provider->enrichOrderCriteria($criteria);
        }

        if ($strategy === OrderVersionStrategy::REQUEST) {
            $resolvedReference = null;

            [$orderVersionContext, $languageAwareContext] = $this->createGenerationContexts(
                $generationRequest->orderId,
                $generationRequest->orderVersionId,
                $apiContext,
            );

            $order = $this->loadOrder($criteria, $generationRequest->orderId, $orderVersionContext);
        } else {
            $reference = $this->referencedDocumentResolver->resolve(
                $generationRequest->orderId,
                $generationRequest->referencedDocumentId,
            );

            [$orderVersionContext, $languageAwareContext] = $this->createGenerationContexts(
                $generationRequest->orderId,
                $strategy === OrderVersionStrategy::REFERENCED
                    ? $reference->orderVersionId
                    : $generationRequest->orderVersionId,
                $apiContext,
            );

            $order = $this->loadOrder($criteria, $generationRequest->orderId, $orderVersionContext);

            if ($strategy === OrderVersionStrategy::BOTH) {
                $reference = $reference->withOrder($this->loadOrder(
                    clone $criteria,
                    $generationRequest->orderId,
                    $orderVersionContext->createWithVersionId($reference->orderVersionId),
                ));
            }

            $resolvedReference = $reference;
        }

        $documentNumber = $generationRequest->documentNumber ?? $this->documentNumberGenerator->generate(
            $generationRequest,
            $order,
            $apiContext,
            $preview,
        );

        $generationRequest = $generationRequest->withDocumentNumber($documentNumber);

        $providerData = $this->collectProviderData(
            $providers,
            new ProviderInput($order, $generationRequest, $resolvedReference),
            $languageAwareContext,
        );

        $renderState = new RenderState();
        $renderInput = new RenderInput(
            documentType: $generationRequest->documentType,
            documentNumber: $documentNumber,
            order: $order,
            data: $providerData,
        );

        foreach ($renderPlan as $format) {
            $renderer = $this->documentRendererRegistry->getRenderer(
                $format,
                $generationRequest->documentType,
            );

            $result = $renderer->renderToString(
                $renderInput,
                $renderState,
                $languageAwareContext,
            );

            $renderState->add($result);
        }

        return [
            'generationRequest' => $generationRequest,
            'renderInput' => $renderInput,
            'renderState' => $renderState,
            'requestedFormats' => $requestedFormats,
            'resolvedReference' => $resolvedReference,
        ];
    }

    /**
     * @param list<AbstractDocumentDataProvider> $providers
     *
     * @throws DocumentV2Exception
     */
    private function resolveOrderVersionStrategy(array $providers, string $documentType): OrderVersionStrategy
    {
        $strategies = [];

        foreach ($providers as $provider) {
            $strategy = $provider->getOrderVersionStrategy();

            if ($strategy !== OrderVersionStrategy::REQUEST) {
                $strategies[$strategy->name] = $strategy;
            }
        }

        if (\count($strategies) > 1) {
            throw DocumentV2Exception::conflictingOrderVersionStrategies($documentType, array_keys($strategies));
        }

        return array_values($strategies)[0] ?? OrderVersionStrategy::REQUEST;
    }

    /**
     * @param list<AbstractDocumentDataProvider> $providers
     *
     * @return array<string, AbstractRenderData>
     */
    private function collectProviderData(
        array $providers,
        ProviderInput $input,
        Context $context,
    ): array {
        $data = [];

        foreach ($providers as $provider) {
            $data[$provider->getKey()] = $provider->provideRenderingData(
                $input,
                $context,
            );
        }

        return $data;
    }

    /**
     * @throws DocumentV2Exception
     *
     * @return array{0: Context, 1: Context}
     */
    private function createGenerationContexts(
        string $orderId,
        string $orderVersionId,
        Context $apiContext,
    ): array {
        $orderVersionContext = $apiContext->createWithVersionId($orderVersionId);
        $languageAwareContext = clone $apiContext;

        $orderLanguageId = $this->loadOrderLanguageId($orderId, $orderVersionContext);

        $langChain = [
            'languageIdChain' => array_values(array_unique(array_filter(
                [$orderLanguageId, ...$apiContext->getLanguageIdChain()]
            ))),
        ];

        $orderVersionContext->assign($langChain);
        $languageAwareContext->assign($langChain);

        return [
            $orderVersionContext,
            $languageAwareContext,
        ];
    }

    /**
     * @throws DocumentV2Exception
     */
    private function loadOrder(Criteria $criteria, string $orderId, Context $orderVersionContext): OrderEntity
    {
        $criteria->setTitle('document-v2-generator::load-order');

        $order = $this->orderRepository->search(
            $criteria,
            $orderVersionContext
        )->getEntities()->first();

        if (!$order instanceof OrderEntity) {
            throw DocumentV2Exception::orderNotFound($orderId);
        }

        return $order;
    }

    /**
     * @throws DocumentV2Exception
     */
    private function loadOrderLanguageId(string $orderId, Context $context): string
    {
        $criteria = (new Criteria([$orderId]))
            ->setTitle('document-v2-generator::load-order-language')
            ->addFields(['languageId']);

        $languageId = $this->orderRepository
            ->search($criteria, $context)
            ->getEntities()
            ->first()
            ?->get('languageId');

        if (!\is_string($languageId)) {
            throw DocumentV2Exception::orderNotFound($orderId);
        }

        return $languageId;
    }

    /**
     * @throws DocumentV2Exception
     */
    private function validateGenerationRequest(DocumentGenerationRequest $generationRequest): void
    {
        if ($generationRequest->requestedFormats === []) {
            throw DocumentV2Exception::missingFormats();
        }

        if ($generationRequest->orderVersionId === Defaults::LIVE_VERSION) {
            throw DocumentV2Exception::liveVersionNotAllowed();
        }
    }

    /**
     * @param list<string> $requestedFormats
     *
     * @throws DocumentV2Exception
     *
     * @return list<string>
     */
    private function normalizeRequestedFormats(array $requestedFormats): array
    {
        $requestedFormats = array_values(array_unique(array_filter($requestedFormats)));

        if ($requestedFormats === []) {
            throw DocumentV2Exception::missingFormats();
        }

        return $requestedFormats;
    }
}
