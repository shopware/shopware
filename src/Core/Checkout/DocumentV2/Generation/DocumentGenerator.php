<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
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
        $this->validateGenerationRequest($generationRequest);

        $requestedFormats = $this->normalizeRequestedFormats($generationRequest->requestedFormats);

        $renderPlan = $this->dependencyResolver->resolve(
            $generationRequest->documentType,
            $requestedFormats,
        );

        $providers = $this->documentDataProviderRegistry->getByDocumentType(
            $generationRequest->documentType,
        );

        $criteria = new Criteria([$generationRequest->orderId]);

        foreach ($providers as $provider) {
            $provider->enrichOrderCriteria($criteria);
        }

        [$orderVersionContext, $languageAwareContext] = $this->createGenerationContexts(
            $generationRequest,
            $apiContext,
        );

        $order = $this->loadOrder(
            $criteria,
            $generationRequest->orderId,
            $orderVersionContext,
        );

        $documentNumber = $generationRequest->documentNumber ?? $this->documentNumberGenerator->generate(
            $generationRequest,
            $order,
            $apiContext,
        );

        $generationRequest = $generationRequest->withDocumentNumber($documentNumber);

        $providerData = $this->collectProviderData(
            $providers,
            $order,
            $generationRequest,
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

        return $this->documentPersister->persist(
            $generationRequest,
            $renderInput,
            $renderState,
            $requestedFormats,
            $apiContext,
        );
    }

    /**
     * @param list<AbstractDocumentDataProvider> $providers
     *
     * @return array<string, AbstractRenderData>
     */
    private function collectProviderData(
        array $providers,
        OrderEntity $order,
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): array {
        $data = [];

        foreach ($providers as $provider) {
            $data[$provider->getKey()] = $provider->provideRenderingData(
                $order,
                $generationRequest,
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
        DocumentGenerationRequest $generationRequest,
        Context $apiContext,
    ): array {
        $orderVersionContext = $apiContext->createWithVersionId($generationRequest->orderVersionId);
        $languageAwareContext = clone $apiContext;

        $orderLanguageId = $this->loadOrderLanguageId($generationRequest, $orderVersionContext);

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
    private function loadOrderLanguageId(DocumentGenerationRequest $generationRequest, Context $context): string
    {
        $criteria = (new Criteria([$generationRequest->orderId]))
            ->setTitle('document-v2-generator::load-order-language')
            ->addFields(['languageId']);

        $languageId = $this->orderRepository
            ->search($criteria, $context)
            ->getEntities()
            ->first()
            ?->get('languageId');

        if (!\is_string($languageId)) {
            throw DocumentV2Exception::orderNotFound($generationRequest->orderId);
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
