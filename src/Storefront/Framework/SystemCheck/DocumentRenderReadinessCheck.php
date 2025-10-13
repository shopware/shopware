<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
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
    Maps document types to their supported file extensions.
    Only document types listed here will be tested.
     */
    private const TESTABLE_DOCUMENT_TYPES = [
        'invoice' => [
            PdfRenderer::FILE_EXTENSION,
            HtmlRenderer::FILE_EXTENSION
        ],
        'delivery_note' => [
            PdfRenderer::FILE_EXTENSION,
            HtmlRenderer::FILE_EXTENSION
        ],
        'zugferd_invoice' => [
            'xml'
        ],
        'zugferd_embedded_invoice'=> [
            PdfRenderer::FILE_EXTENSION
        ],
    ];

    /**
     * @param SalesChannelDomainUtil $util
     * @param Connection $connection
     * @param AbstractDocumentRenderer $documentRenderers
     */
    public function __construct(
        private readonly SalesChannelDomainUtil $util,
        private readonly Connection $connection,
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

            if (!array_key_exists($documentType, self::TESTABLE_DOCUMENT_TYPES)) {
                $extra[] = [
                    'documentType' => $documentType,
                    'message' => 'This document type is not covered by this check; skipping.',
                ];
                continue;
            }

            $orderId = $this->getOrderId($documentType);

            if (!$orderId) {
                $extra[] = [
                    'documentType' => $documentType,
                    'message' => 'No order with document of this type found; skipping.',
                ];

                continue;
            }

            $fileTypes = self::TESTABLE_DOCUMENT_TYPES[$documentType];

            foreach ($fileTypes as $fileType) {
                $operation = new DocumentGenerateOperation(
                    $orderId,
                    $fileType,
                    [],
                    null,
                    false,
                    true
                );

                try {
                    $processedTemplate = $renderer->render(
                        [$orderId => $operation],
                        $context,
                        new DocumentRendererConfig()
                    );

                    $error = $processedTemplate->getErrors()[$orderId] ?? [];

                    if($error) {
                        $result[Status::FAILURE->name] = Status::FAILURE;
                        $extra[] = [
                            'documentType' => $documentType,
                            'fileType' => $fileType,
                            'message' => 'Rendering failed with errors: ' . $error->getMessage(),
                        ];
                    }

                    $success = $processedTemplate->getSuccess()[$orderId] ?? null;

                    if ($success === null) {
                        $result[Status::FAILURE->name] = Status::FAILURE;
                        $extra[] = [
                            'documentType' => $documentType,
                            'fileType' => $fileType,
                            'message' => 'Rendering failed without exception and without result.',
                        ];
                    }

                    $content = $processedTemplate->getSuccess()[$orderId]->getContent();

                    if (\strlen($content) < 10) {
                        $result[Status::FAILURE->name] = Status::FAILURE;
                        $extra[] = [
                            'documentType' => $documentType,
                            'fileType' => $fileType,
                            'message' => 'rendered content is to less or empty for type ' . $documentType . ' and fileType ' . $fileType,
                        ];
                    }
                } catch (\Throwable $e) {
                    $result[Status::FAILURE->name] = Status::FAILURE;
                    $extra[] = [
                        'documentType' => $documentType,
                        'fileType' => $fileType,
                        'message' => 'Rendering failed with exception: ' . $e->getMessage(),
                    ];
                    continue;
                }

                $result[Status::OK->name] = Status::OK;
                $extra[] = [
                    'documentType' => $documentType,
                    'fileType' => $fileType,
                    'message' => 'Rendering successful.',
                ];
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
     * Fetches an order ID that already has a generated document of the specified type
     * to avoid generating documents for orders that maybe do not meet all requirements.
     */
    private function getOrderId(string $type)
    {
        $sql = '
            SELECT
                d.order_id as id
            FROM `document` AS d
                INNER JOIN `document_type` AS dt ON d.document_type_id = dt.id
            WHERE
                dt.technical_name = :type
            LIMIT 1
        ';

        $binaryId = $this->connection->fetchOne($sql, ['type' => $type]);

        return $binaryId ? Uuid::fromBytesToHex($binaryId) : null;
    }
}
