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
        private DocumentEntityPersister $documentEntityPersister,
        private DocumentDependencyResolver $dependencyResolver,
        private EntityRepository $orderRepository,
    ) {
    }

    /**
     * Generates one logical document with one or more persisted document_file artifacts.
     *
     * For example, if the caller requests only `pdf` and the PDF renderer depends on `html`,
     * both formats are rendered, but only the PDF result is persisted as a document_file.
     */
    public function generate(DocumentGenerationContext $generationContext): DocumentEntity
    {
        $this->validateGenerationContext($generationContext);

        $requestedFormats = $this->normalizeFormats($generationContext->formats);

        $renderPlan = $this->dependencyResolver->resolve(
            $generationContext->documentType,
            $requestedFormats,
        );

        $providers = $this->documentDataProviderRegistry->getByDocumentType(
            $generationContext->documentType,
        );

        $criteria = new Criteria([$generationContext->orderId]);

        foreach ($providers as $provider) {
            $provider->enrichOrderCriteria($criteria);
        }

        $renderContext = $this->createRenderContext($generationContext);

        $order = $this->loadOrder(
            $criteria,
            $renderContext,
            $generationContext->orderId
        );

        $documentNumber = $generationContext->documentNumber ?? $this->documentNumberGenerator->generate(
            $generationContext,
            $order,
        );

        $providerData = $this->collectProviderData($providers, $order, $generationContext);

        $renderState = new RenderState();
        $renderInput = new RenderInput(
            documentType: $generationContext->documentType,
            documentNumber: $documentNumber,
            order: $order,
            data: $providerData,
            renderContext: $renderContext,
        );

        foreach ($renderPlan as $format) {
            $renderer = $this->documentRendererRegistry->getRenderer(
                $format,
                $generationContext->documentType,
            );

            $result = $renderer->renderToString(
                $renderInput,
                $renderState,
            );

            $renderState->add($result);
        }

        $persistedFiles = [];

        foreach ($requestedFormats as $format) {
            $renderer = $this->documentRendererRegistry->getRenderer(
                $format,
                $generationContext->documentType,
            );

            $mediaId = $renderer->persistToFile(
                $renderInput,
                $renderState->require($format)
            );

            $persistedFiles[$format] = $mediaId;
        }

        return $this->documentEntityPersister->persist(
            $generationContext,
            $renderInput,
            $persistedFiles,
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
        DocumentGenerationContext $generationContext
    ): array {
        $data = [];

        foreach ($providers as $provider) {
            $data[$provider->getKey()] = $provider->provideRenderingData(
                $order,
                $generationContext,
            );
        }

        return $data;
    }

    /**
     * @throws DocumentV2Exception
     */
    private function createRenderContext(DocumentGenerationContext $generationContext): Context
    {
        $renderContext = $generationContext->apiContext
            ->createWithVersionId($generationContext->orderVersionId);

        $orderLanguageId = $this->loadOrderLanguageId($generationContext);

        $renderContext->assign([
            'languageIdChain' => array_values(array_unique(array_filter(
                [$orderLanguageId, ...$renderContext->getLanguageIdChain()]
            ))),
        ]);

        return $renderContext;
    }

    /**
     * @throws DocumentV2Exception
     */
    private function loadOrder(Criteria $criteria, Context $context, string $orderId): OrderEntity
    {
        $criteria->setTitle('document-v2-generator::load-order');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('language');
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('deliveries.shippingMethod');

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();

        if (!$order instanceof OrderEntity) {
            throw DocumentV2Exception::orderNotFound($orderId);
        }

        return $order;
    }

    /**
     * @throws DocumentV2Exception
     */
    private function loadOrderLanguageId(DocumentGenerationContext $generationContext): string
    {
        $criteria = (new Criteria([$generationContext->orderId]))
            ->setTitle('document-v2-generator::load-order-language')
            ->addFields(['languageId']);

        $context = $generationContext->apiContext
            ->createWithVersionId($generationContext->orderVersionId);

        $order = $this->orderRepository->search(
            $criteria,
            $context,
        )->getEntities()->first();

        if (!$order instanceof OrderEntity) {
            throw DocumentV2Exception::orderNotFound($generationContext->orderId);
        }

        return $order->getLanguageId();
    }

    /**
     * @throws DocumentV2Exception
     */
    private function validateGenerationContext(DocumentGenerationContext $generationContext): void
    {
        if ($generationContext->formats === []) {
            throw DocumentV2Exception::missingFormats();
        }

        if ($generationContext->orderVersionId === Defaults::LIVE_VERSION) {
            throw DocumentV2Exception::liveVersionNotAllowed();
        }
    }

    /**
     * @param list<string> $formats
     *
     * @throws DocumentV2Exception
     *
     * @return list<string>
     */
    private function normalizeFormats(array $formats): array
    {
        $formats = array_values(array_unique(array_filter($formats)));

        if ($formats === []) {
            throw DocumentV2Exception::missingFormats();
        }

        return $formats;
    }
}
