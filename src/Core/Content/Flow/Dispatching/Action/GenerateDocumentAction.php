<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Exception\GenerateDocumentActionException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

/**
 * @package business-ops
 *
 * @internal
 *
 * @deprecated tag:v6.5.0 - reason:remove-subscriber - FlowActions won't be executed over the event system anymore,
 * therefore the actions won't implement the EventSubscriberInterface anymore.
 */
class GenerateDocumentAction extends FlowAction implements DelayableAction
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
     * @deprecated tag:v6.5.0 - Property $connection will be removed due to unused
     */
    private NumberRangeValueGeneratorInterface $valueGenerator;

    private LoggerInterface $logger;

    /**
     * @internal
     */
    public function __construct(
        DocumentService $documentService,
        DocumentGenerator $documentGenerator,
        NumberRangeValueGeneratorInterface $valueGenerator,
        Connection $connection,
        LoggerInterface $logger
    ) {
        $this->documentService = $documentService;
        $this->documentGenerator = $documentGenerator;
        $this->connection = $connection;
        $this->valueGenerator = $valueGenerator;
        $this->logger = $logger;
    }

    public static function getName(): string
    {
        return 'action.generate.document';
    }

    /**
     * @deprecated tag:v6.5.0 - reason:remove-subscriber - Will be removed
     */
    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('v6.5.0.0')) {
            return [];
        }

        return [
            self::getName() => 'handle',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function requirements(): array
    {
        return [OrderAware::class];
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed, implement handleFlow instead
     */
    public function handle(FlowEvent $event): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $baseEvent = $event->getEvent();
        if (!$baseEvent instanceof OrderAware || !$baseEvent instanceof SalesChannelAware) {
            return;
        }

        $this->generate($baseEvent->getContext(), $event->getConfig(), $baseEvent->getOrderId(), $baseEvent->getSalesChannelId());
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasStore(OrderAware::ORDER_ID) || !$flow->hasStore(MailAware::SALES_CHANNEL_ID)) {
            return;
        }

        $this->generate($flow->getContext(), $flow->getConfig(), $flow->getStore(OrderAware::ORDER_ID), $flow->getStore(MailAware::SALES_CHANNEL_ID));
    }

    /**
     * @param array<string, mixed> $eventConfig
     */
    private function generate(Context $context, array $eventConfig, string $orderId, string $salesChannelId): void
    {
        if (\array_key_exists('documentType', $eventConfig)) {
            $this->generateDocument($eventConfig, $context, $orderId, $salesChannelId);

            return;
        }

        $documentsConfig = $eventConfig['documentTypes'];

        if (!$documentsConfig) {
            return;
        }

        // Invoice document should be created first
        foreach ($documentsConfig as $index => $config) {
            if ($config['documentType'] === InvoiceGenerator::INVOICE) {
                $this->generateDocument($config, $context, $orderId, $salesChannelId);
                unset($documentsConfig[$index]);

                break;
            }
        }

        foreach ($documentsConfig as $config) {
            $this->generateDocument($config, $context, $orderId, $salesChannelId);
        }
    }

    /**
     * @param array<string, mixed> $eventConfig
     */
    private function generateDocument(array $eventConfig, Context $context, string $orderId, string $salesChannelId): void
    {
        $documentType = $eventConfig['documentType'];
        $documentRangerType = $eventConfig['documentRangerType'];

        if (!$documentType || !$documentRangerType) {
            return;
        }

        if (Feature::isActive('v6.5.0.0')) {
            $fileType = $eventConfig['fileType'] ?? FileTypes::PDF;
            $config = $eventConfig['config'] ?? [];
            $static = $eventConfig['static'] ?? false;

            $operation = new DocumentGenerateOperation($orderId, $fileType, $config, null, $static);

            $result = $this->documentGenerator->generate($documentType, [$orderId => $operation], $context);

            if (!empty($result->getErrors())) {
                foreach ($result->getErrors() as $error) {
                    $this->logger->error($error->getMessage());
                }
            }

            return;
        }

        $documentNumber = $this->valueGenerator->getValue(
            $documentRangerType,
            $context,
            $salesChannelId
        );

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $eventConfig['documentNumber'] = $documentNumber;
        $eventConfig['documentDate'] = $now;

        $customConfig = $this->getEventCustomConfig(
            $documentType,
            $documentNumber,
            $now,
            $orderId
        );

        $eventConfig['custom'] = $customConfig;

        $documentConfig = DocumentConfigurationFactory::createConfiguration($eventConfig);

        $this->documentService->create(
            $orderId,
            $documentType,
            $eventConfig['fileType'] ?? FileTypes::PDF,
            $documentConfig,
            $context,
            $customConfig['referencedInvoiceId'] ?? null,
            $eventConfig['static'] ?? false
        );
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @throws Exception
     *
     * @return array<string, mixed>
     */
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
