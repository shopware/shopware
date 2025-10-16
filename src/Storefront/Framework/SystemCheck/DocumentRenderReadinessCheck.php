<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\SystemCheck\Util\AbstractSalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Order\OrderStates;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentRenderReadinessCheck extends BaseCheck
{
    /*
     * Only listed document types will be tested.
     */
    private const TESTABLE_DOCUMENT_TYPES = [
        InvoiceRenderer::TYPE,
        DeliveryNoteRenderer::TYPE,
        ZugferdRenderer::TYPE,
        ZugferdEmbeddedRenderer::TYPE,
    ];

    /**
     * @param SalesChannelDomainUtil $util
     * @param Connection $connection
     * @param DocumentConfigLoader $documentConfigLoader
     * @param iterable<AbstractDocumentRenderer> $documentRenderers
     */
    public function __construct(
        private readonly SalesChannelDomainUtil $util,
        private readonly Connection $connection,
        private readonly DocumentConfigLoader $documentConfigLoader,
        private readonly iterable $documentRenderers,
    ) {
    }

    public function run(): Result
    {
        return $this->util->runAsSalesChannelRequest(
            fn () => $this->util->runWhileTrustingAllHosts(
                fn () => $this->doRun()
            )
        );
    }

    public function category(): Category
    {
        return Category::FEATURE;
    }

    public function name(): string
    {
        return 'DocumentRenderReadinessCheck';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::readiness();
    }

    private function doRun(): Result
    {
        $orderCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `order`');

        if ($orderCount === 0) {
            return $this->util->createEmptyResult(
                $this->name(),
                'No orders found; document previews are skipped.'
            );
        }

        $context = Context::createDefaultContext();
        $result = [];
        $extra = [];

        foreach ($this->documentRenderers as $renderer) {
            $documentType = $renderer->supports();

            if (!in_array($documentType, self::TESTABLE_DOCUMENT_TYPES, true)) {
                $result[Status::OK->name] = Status::OK;
                $extra = [
                    'documentType' => $documentType,
                    'status' => Status::SKIPPED->name,
                    'message' => 'This document type is not covered by this check.',
                ];
                continue;
            }

            $orderData = $this->getOrderData($documentType);

            if ($orderData === null) {
                $result[Status::OK->name] = Status::OK;
                $extra = [
                    'documentType' => $documentType,
                    'status' => Status::SKIPPED->name,
                    'message' => 'No order with document of this type found.',
                ];

                continue;
            }

            $fileTypes = $this->resolveFileTypes($documentType, $orderData['sales_channel_id'], $context);
            if (empty($fileTypes)) {
                $result[Status::FAILURE->name] = Status::OK;
                $extra = [
                    'documentType' => $documentType,
                    'status' => Status::SKIPPED->name,
                    'message' => 'No file types configured for document type ' . $documentType . '; skipping.',
                ];

                continue;
            }

            foreach ($fileTypes as $fileType) {
                $operation = new DocumentGenerateOperation(
                    $orderData['order_id'],
                    $fileType,
                    [],
                    null,
                    false,
                    true
                );

                try {
                    $processedTemplate = $renderer->render(
                        [$orderData['order_id'] => $operation],
                        $context,
                        new DocumentRendererConfig()
                    );

                    $error = $processedTemplate->getErrors()[$orderData['order_id']] ?? null;
                    $success = $processedTemplate->getSuccess()[$orderData['order_id']] ?? null;

                    if($error) {
                        $result[Status::FAILURE->name] = Status::FAILURE;
                        $extra = [
                            'documentType' => $documentType,
                            'fileType' => $fileType,
                            'status' => Status::FAILURE->name,
                            'message' => 'Rendering failed with errors: ' . $error->getMessage(),
                        ];

                        continue;
                    }

                    if ($success === null) {
                        $result[Status::FAILURE->name] = Status::FAILURE;
                        $extra = [
                            'documentType' => $documentType,
                            'fileType' => $fileType,
                            'status' => Status::FAILURE->name,
                            'message' => 'Rendering failed without exception (no success result).'
                        ];

                        continue;
                    }

                    $result[Status::OK->name] = Status::OK;
                    $extra = [
                        'documentType' => $documentType,
                        'fileType' => $fileType,
                        'status' => Status::OK->name,
                        'message' => 'Rendering successful.'
                    ];

                } catch (\Throwable $e) {
                    $result[Status::FAILURE->name] = Status::FAILURE;
                    $extra = [
                        'documentType' => $documentType,
                        'fileType' => $fileType,
                        'status' => Status::FAILURE->name,
                        'message' => 'Rendering failed with exception: ' . $e->getMessage(),
                    ];

                    continue;
                }
            }
        }

        $finalStatus = \count($result) === 1 ? current($result) : Status::ERROR;

        return new Result(
            $this->name(),
            $finalStatus,
            $finalStatus === Status::OK ? 'All documents rendered successfully.' : 'Some or all documents failed to render.',
            $finalStatus === Status::OK,
            $extra
        );
    }

    /**
     * Fetches an order that already has a generated document of the specified type
     * to avoid generating documents for orders that maybe do not meet all requirements.
     *
     * @return array<string, string>|null
     */
    private function getOrderData(string $type): ?array
    {
        $sql = '
            SELECT
                d.order_id as order_id,
                o.sales_channel_id
            FROM `document` AS d
                INNER JOIN `document_type` AS dt ON d.document_type_id = dt.id
                LEFT JOIN `order` AS o ON d.order_id = o.id
            WHERE
                dt.technical_name = :type
            LIMIT 1
        ';

        $data = $this->connection->fetchAssociative($sql, ['type' => $type]);

        if ($data === false) {
            return null;
        }

        $data['order_id'] = Uuid::fromBytesToHex($data['order_id']);
        $data['sales_channel_id'] = Uuid::fromBytesToHex($data['sales_channel_id']);

        return $data;
    }

    /**
     * Special handling for ZUGFeRD types, as they do not have a config in the database.
     *
     * @return array<int, string>|array{}
     */
    private function resolveFileTypes(string $documentType, string $salesChannelId, Context $context): array
    {
        if ($documentType === ZugferdRenderer::TYPE){
            return ['xml'];
        }

        if ($documentType === ZugferdEmbeddedRenderer::TYPE){
            return [PdfRenderer::FILE_EXTENSION];
        }

        return $this->documentConfigLoader
            ->load($documentType, $salesChannelId, $context)
            ->getFileTypes();
    }
}
