<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Flow\Exception\GenerateDocumentActionException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Event\DelayAware;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;

class GenerateDocumentAction extends FlowAction
{
    /**
     * @deprecated tag:v6.5.0 - Property $documentService will be removed due to unused
     */
    protected DocumentService $documentService;

    /**
     * @deprecated tag:v6.5.0 - Property $connection will be removed due to unused
     */
    protected Connection $connection;

    private DocumentGenerator $documentGenerator;

    /**
     * @internal
     */
    public function __construct(
        DocumentService $documentService,
        DocumentGenerator $documentGenerator,
        Connection $connection
    ) {
        $this->documentService = $documentService;
        $this->documentGenerator = $documentGenerator;
        $this->connection = $connection;
    }

    public static function getName(): string
    {
        return 'action.generate.document';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class, DelayAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        $baseEvent = $event->getEvent();

        $eventConfig = $event->getConfig();

        if (!$baseEvent instanceof OrderAware || !$baseEvent instanceof SalesChannelAware) {
            return;
        }

        if (\array_key_exists('documentType', $eventConfig)) {
            $this->generateDocument($eventConfig, $baseEvent);

            return;
        }

        $documentsConfig = $eventConfig['documentTypes'];

        if (!$documentsConfig) {
            return;
        }

        // Invoice document should be created first
        foreach ($documentsConfig as $index => $config) {
            if ($config['documentType'] === InvoiceGenerator::INVOICE) {
                $this->generateDocument($config, $baseEvent);
                unset($documentsConfig[$index]);

                break;
            }
        }

        foreach ($documentsConfig as $config) {
            $this->generateDocument($config, $baseEvent);
        }
    }

    /**
     * @param OrderAware&SalesChannelAware $baseEvent
     */
    private function generateDocument(array $eventConfig, $baseEvent): void
    {
        $documentType = $eventConfig['documentType'];
        $documentRangerType = $eventConfig['documentRangerType'];

        if (!$documentType || !$documentRangerType) {
            return;
        }

        $fileType = $eventConfig['fileType'] ?? FileTypes::PDF;
        $config = $eventConfig['config'] ?? [];
        $static = $eventConfig['static'] ?? false;

        $operation = new DocumentGenerateOperation($baseEvent->getOrderId(), $fileType, $config, null, $static);

        $this->documentGenerator->generate($documentType, [$baseEvent->getOrderId() => $operation], $baseEvent->getContext())->first();
    }
}
