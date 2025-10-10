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
    /* some document types depend on invoice document or specific line-items */
    private const TESTABLE_DOCUMENT_TYPES = [
        'invoice',
        'delivery_note',
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
            $type = $renderer->supports();
            var_dump($type);

            if (!in_array($type, self::TESTABLE_DOCUMENT_TYPES, true)) {
                $extra[] = [
                    'documentType' => $type,
                    'message' => 'This document type is not covered by this check; skipping.',
                ];
                continue;
            }

            // choose an order that has an already generated document of this type
            $orderId = $this->getOrderId($type);

            // skip if no document of this type already exists for this order
            if (!$orderId) {
                $extra[] = [
                    'documentType' => $type,
                    'message' => 'No order with document of this type found; skipping.',
                ];

                continue;
            }

            // check which fileTypes are supported via document-config
            $fileTypes = [PdfRenderer::FILE_EXTENSION, HtmlRenderer::FILE_EXTENSION];

            foreach ($fileTypes as $fileType) {
                $operation = new DocumentGenerateOperation(
                    $orderId,
                    $fileType,
                    [],
                    null,
                    false,
                    true
                );

                $processedTemplate = $renderer->render(
                    [$orderId => $operation],
                    $context,
                    new DocumentRendererConfig()
                );

                $error = $processedTemplate->getErrors()[$orderId] ?? [];

                if($error) {
                    $result[Status::FAILURE->name] = Status::FAILURE;
                    $extra[] = [
                        'documentType' => $type,
                        'fileType' => $fileType,
                        'message' => 'Rendering failed with errors: ' . $error->getMessage(),
                    ];
                }

                $success = $processedTemplate->getSuccess()[$orderId] ?? null;

                if ($success === null) {
                    $result[Status::FAILURE->name] = Status::FAILURE;
                    $extra[] = [
                        'documentType' => $type,
                        'fileType' => $fileType,
                        'message' => 'Rendering failed without exception and without result.',
                    ];
                }

                $content = $processedTemplate->getSuccess()[$orderId]->getContent();

                if (\strlen($content) < 10) {
                    $result[Status::FAILURE->name] = Status::FAILURE;
                    $extra[] = [
                        'documentType' => $type,
                        'fileType' => $fileType,
                        'message' => 'rendered content is to less or empty for type ' . $type . ' and fileType ' . $fileType,
                    ];
                }

                $result[Status::OK->name] = Status::OK;
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

    private function getOrderId(string $type)
    {
        $sql = '
            SELECT
                LOWER(HEX(d.order_id)) as id
            FROM `document` AS d
                INNER JOIN `document_type` AS dt ON d.document_type_id = dt.id
            WHERE
                dt.technical_name = :type
            LIMIT 1
        ';

        return $this->connection->fetchOne($sql, ['type' => $type]);
    }
}
