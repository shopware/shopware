<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Content\Flow\Exception\GenerateDocumentActionException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

class GenerateDocumentAction extends FlowAction
{
    protected DocumentService $documentService;

    protected Connection $connection;

    private NumberRangeValueGeneratorInterface $valueGenerator;

    public function __construct(
        DocumentService $documentService,
        NumberRangeValueGeneratorInterface $valueGenerator,
        Connection $connection
    ) {
        $this->documentService = $documentService;
        $this->valueGenerator = $valueGenerator;
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
        return [OrderAware::class];
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

        $documentNumber = $this->valueGenerator->getValue(
            $documentRangerType,
            $baseEvent->getContext(),
            $baseEvent->getSalesChannelId()
        );

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $eventConfig['documentNumber'] = $documentNumber;
        $eventConfig['documentDate'] = $now;

        $customConfig = $this->getEventCustomConfig(
            $documentType,
            $documentNumber,
            $now,
            $baseEvent->getOrderId()
        );

        $eventConfig['custom'] = $customConfig;

        $documentConfig = DocumentConfigurationFactory::createConfiguration($eventConfig);

        $this->documentService->create(
            $baseEvent->getOrderId(),
            $documentType,
            $eventConfig['fileType'] ?? FileTypes::PDF,
            $documentConfig,
            $baseEvent->getContext(),
            $customConfig['referencedInvoiceId'] ?? null,
            $eventConfig['static'] ?? false
        );
    }

    private function getEventCustomConfig(string $documentType, string $documentNumber, string $now, string $orderId): array
    {
        switch ($documentType) {
            case InvoiceGenerator::INVOICE:
                return ['invoiceNumber' => $documentNumber];
            case DeliveryNoteGenerator::DELIVERY_NOTE:
                return [
                    'deliveryNoteNumber' => $documentNumber,
                    'deliveryDate' => $now,
                    'deliveryNoteDate' => $now,
                ];
            case StornoGenerator::STORNO:
            case CreditNoteGenerator::CREDIT_NOTE:
                return $this->getConfigWithReferenceDoc($documentType, $documentNumber, $orderId);
            default:
                return [];
        }
    }

    private function getConfigWithReferenceDoc(string $documentType, string $documentNumber, string $orderId): array
    {
        $referencedInvoiceDocument = $this->connection->fetchAssociative(
            'SELECT LOWER (HEX(`document`.`id`)) as `id` , `document`.`config` as `config`
                    FROM `document` JOIN `document_type` ON `document`.`document_type_id` = `document_type`.`id`
                    WHERE `document_type`.`technical_name` = :techName AND hex(`document`.`order_id`) = :orderId
                    ORDER BY `document`.`created_at` DESC LIMIT 1',
            [
                'techName' => InvoiceGenerator::INVOICE,
                'orderId' => $orderId,
            ]
        );

        if (empty($referencedInvoiceDocument)) {
            throw new GenerateDocumentActionException('Cannot generate ' . $documentType . ' document because no invoice document exists. OrderId: ' . $orderId);
        }

        if ($documentType === CreditNoteGenerator::CREDIT_NOTE && !$this->hasCreditItem($orderId)) {
            throw new GenerateDocumentActionException('Cannot generate the credit note document because no credit items exist. OrderId: ' . $orderId);
        }

        $documentRefer = json_decode($referencedInvoiceDocument['config'], true);
        $documentNumberRefer = $documentRefer['custom']['invoiceNumber'];

        return array_filter([
            'invoiceNumber' => $documentNumberRefer,
            'stornoNumber' => $documentType === StornoGenerator::STORNO ? $documentNumber : null,
            'creditNoteNumber' => $documentType === CreditNoteGenerator::CREDIT_NOTE ? $documentNumber : null,
            'referencedInvoiceId' => $referencedInvoiceDocument['id'],
        ]);
    }

    private function hasCreditItem(string $orderId): bool
    {
        $lineItem = $this->connection->fetchFirstColumn(
            'SELECT 1 FROM `order_line_item` WHERE hex(`order_id`) = :orderId and `type` = :itemType LIMIT 1',
            [
                'orderId' => $orderId,
                'itemType' => LineItem::CREDIT_LINE_ITEM_TYPE,
            ]
        );

        return !empty($lineItem);
    }
}
