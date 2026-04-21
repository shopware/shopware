<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
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
        private DocumentDependencyResolver $dependencyResolver,
        private EntityRepository $orderRepository,
    ) {
    }

    public function generate(DocumentGenerationContext $generationContext): RenderState
    {
        $this->validateGenerationContext($generationContext);

        $requestedFormats = $this->normalizeFormats($generationContext->getFormats());

        $renderPlan = $this->dependencyResolver->resolve(
            $generationContext->getDocumentType(),
            $requestedFormats,
        );

        $providers = $this->documentDataProviderRegistry->getByDocumentType(
            $generationContext->getDocumentType()
        );

        $criteria = new Criteria([$generationContext->getOrderId()]);

        foreach ($providers as $provider) {
            $provider->enrichOrderCriteria($criteria);
        }

        $order = $this->loadOrder($criteria, $generationContext);

        $documentNumber = $generationContext->getDocumentNumber() ?? $this->documentNumberGenerator->generate(
            $generationContext,
            $order,
        );

        $providerData = $this->collectProviderData($providers, $order);

        $renderState = new RenderState();
        $renderInput = new RenderInput(
            documentType: $generationContext->getDocumentType(),
            documentNumber: $documentNumber,
            order: $order,
            data: $providerData,
        );

        foreach ($renderPlan as $format) {
            $renderer = $this->documentRendererRegistry->getRenderer(
                $format,
                $generationContext->getDocumentType(),
            );

            $result = $renderer->renderToString(
                $renderInput,
                $renderState,
            );

            $renderState->add($result);
        }

        return $renderState;
    }

    /**
     * @param list<AbstractDocumentDataProvider> $providers
     *
     * @return array<string, RenderData>
     */
    private function collectProviderData(array $providers, OrderEntity $order): array
    {
        $data = [];

        foreach ($providers as $provider) {
            $data[$provider->getKey()] = $provider->provideRenderingData($order);
        }

        return $data;
    }

    private function loadOrder(Criteria $criteria, DocumentGenerationContext $generationContext): OrderEntity
    {
        $context = $generationContext->getContext();

        $criteria->setTitle('document-v2-generator::load-order');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('language');
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('deliveries.shippingMethod');

        $versionContext = $context->createWithVersionId($generationContext->getOrderVersionId());

        $order = $this->orderRepository->search($criteria, $versionContext)->getEntities()->first();

        if (!$order instanceof OrderEntity) {
            throw DocumentV2Exception::orderNotFound($generationContext->getOrderId());
        }

        return $order;
    }

    private function validateGenerationContext(DocumentGenerationContext $generationContext): void
    {
        if ($generationContext->getFormats() === []) {
            throw DocumentV2Exception::missingFormats();
        }

        if ($generationContext->getOrderVersionId() === Defaults::LIVE_VERSION) {
            throw DocumentV2Exception::liveVersionNotAllowed();
        }
    }

    /**
     * @param list<string> $formats
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
