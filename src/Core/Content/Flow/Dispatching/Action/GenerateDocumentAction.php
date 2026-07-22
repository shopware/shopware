<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator as DocumentV2Generator;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;
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
        private readonly DocumentV2Generator $documentV2Generator,
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

        $this->generate($flow->getContext(), $flow->getConfig(), $flow->getData(OrderAware::ORDER_ID));
    }

    /**
     * @param array<string, mixed> $eventConfig
     */
    private function generate(Context $context, array $eventConfig, string $orderId): void
    {
        if (Feature::isActive('DOCUMENT_GENERATION_REWORK')) {
            $this->generateDocumentV2($eventConfig, $context, $orderId);

            return;
        }

        if (\array_key_exists('documentType', $eventConfig)) {
            $this->generateDocument($eventConfig, $context, $orderId);

            return;
        }

        $documentsConfig = $eventConfig['documentTypes'];

        if (!$documentsConfig) {
            return;
        }

        // Invoice document should be created first
        foreach ($documentsConfig as $index => $config) {
            if ($config['documentType'] === InvoiceRenderer::TYPE) {
                $this->generateDocument($config, $context, $orderId);
                unset($documentsConfig[$index]);

                break;
            }
        }

        foreach ($documentsConfig as $config) {
            $this->generateDocument($config, $context, $orderId);
        }
    }

    /**
     * @param array<string, mixed> $eventConfig
     */
    private function generateDocument(array $eventConfig, Context $context, string $orderId): void
    {
        $documentType = $eventConfig['documentType'];
        $documentRangerType = $eventConfig['documentRangerType'];

        if (!$documentType || !$documentRangerType) {
            return;
        }

        $fileType = $eventConfig['fileType'] ?? FileTypes::PDF;
        $config = $eventConfig['config'] ?? [];
        $static = $eventConfig['static'] ?? false;

        $operation = new DocumentGenerateOperation($orderId, $fileType, $config, null, $static);

        $result = $this->documentGenerator->generate($documentType, [$orderId => $operation], $context);

        foreach ($result->getErrors() as $error) {
            $this->logger->error($error->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $eventConfig
     */
    private function generateDocumentV2(array $eventConfig, Context $context, string $orderId): void
    {
        $documentType = $eventConfig['documentType'] ?? null;
        $fileFormats = $eventConfig['fileFormats'] ?? [];

        if (!$documentType || !$fileFormats) {
            return;
        }

        $generationRequest = new DocumentGenerationRequest(
            $orderId,
            $documentType,
            $fileFormats,
        );

        try {
            $this->documentV2Generator->generate($generationRequest, $context);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }
    }
}
