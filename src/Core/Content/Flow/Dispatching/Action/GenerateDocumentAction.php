<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\A11yRenderedDocumentAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class GenerateDocumentAction extends FlowAction implements DelayableAction
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DocumentGenerator $documentGenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getName(): string
    {
        return 'action.generate.document';
    }

    /**
     * @return list<string>
     */
    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(OrderAware::ORDER_ID)) {
            return;
        }

        $this->generate($flow->getContext(), $flow->getConfig(), $flow->getData(OrderAware::ORDER_ID), $flow);
    }

    /**
     * @param array<string, mixed> $eventConfig
     */
    private function generate(Context $context, array $eventConfig, string $orderId, StorableFlow $flow): void
    {
        if (\array_key_exists('documentType', $eventConfig)) {
            $this->generateDocument($eventConfig, $context, $orderId);

            return;
        }

        $documentsConfig = $eventConfig['documentTypes'];

        if (!$documentsConfig) {
            return;
        }

        $allDocumentIds = [];

        // Invoice document should be created first
        foreach ($documentsConfig as $index => $config) {
            if ($config['documentType'] === InvoiceRenderer::TYPE) {
                $ids = $this->generateDocument($config, $context, $orderId, $flow);
                $allDocumentIds = array_merge($allDocumentIds, $ids);
                unset($documentsConfig[$index]);

                break;
            }
        }

        foreach ($documentsConfig as $config) {
            $ids = $this->generateDocument($config, $context, $orderId, $flow);
            $allDocumentIds = array_merge($allDocumentIds, $ids);
        }

        if (!empty($allDocumentIds)) {
            $flow->setStore(A11yRenderedDocumentAware::A11Y_DOCUMENT_IDS, $allDocumentIds);
        }
    }

    /**
     * @param array<string, mixed> $eventConfig
     */
    private function generateDocument(array $eventConfig, Context $context, string $orderId): array
    {
        $documentType = $eventConfig['documentType'];
        $documentRangerType = $eventConfig['documentRangerType'];

        if (!$documentType || !$documentRangerType) {
            return [];
        }

        $fileType = $eventConfig['fileType'] ?? FileTypes::PDF;
        $config = $eventConfig['config'] ?? [];
        $static = $eventConfig['static'] ?? false;

        $operation = new DocumentGenerateOperation($orderId, $fileType, $config, null, $static);

        $result = $this->documentGenerator->generate($documentType, [$orderId => $operation], $context);

        foreach ($result->getErrors() as $error) {
            $this->logger->error($error->getMessage());
        }

        $success = $result->getSuccess();

        $ids = [];
        foreach ($success as $document) {
            $ids[] = $document->getId();
        }

        return $ids;
    }
}
