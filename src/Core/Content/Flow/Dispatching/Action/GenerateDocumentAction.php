<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
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

    private LoggerInterface $logger;

    public function __construct(
        DocumentService $documentService,
        NumberRangeValueGeneratorInterface $valueGenerator,
        Connection $connection,
        LoggerInterface $logger
    ) {
        $this->documentService = $documentService;
        $this->valueGenerator = $valueGenerator;
        $this->connection = $connection;
        $this->logger = $logger;
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
        if (!$baseEvent instanceof OrderAware || !$baseEvent instanceof SalesChannelAware) {
            return;
        }

        $eventConfig = $event->getConfig();
        $documentType = $eventConfig['documentType'];
        $documentRangerType = $eventConfig['documentRangerType'];

        if (!$documentType || !$documentRangerType) {
            return;
        }

        $documentNumber = $this->valueGenerator->getValue(
            $eventConfig['documentRangerType'],
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

        if (empty($customConfig)) {
            return;
        }

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
            $this->logger->info(
                'Can not generate ' . $documentType . ' document because no invoice document exists. OrderId: ' . $orderId
            );

            return [];
        }

        if ($documentType === CreditNoteGenerator::CREDIT_NOTE && !$this->hasCreditItem($orderId)) {
            $this->logger->info(
                'Can not generate the credit note document because no credit items exist. OrderId: ' . $orderId
            );

            return [];
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
