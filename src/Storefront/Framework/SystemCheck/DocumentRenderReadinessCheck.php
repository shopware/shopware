<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
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
    private const DEPEND_ON_INVOICE = ['credit_note', 'storno'];
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
        // count orders > skip if no orders
        var_dump((int) $this->connection->fetchOne('SELECT COUNT(*) FROM `order`'));

        $orderCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `order`');

        if ($orderCount === 0) {
            return new Result(
                $this->name(),
                Status::SKIPPED,
                'no orders found; document previews are skipped',
                true,
                []
            );
        }

        $context = Context::createDefaultContext();


        foreach ($this->documentRenderers as $renderer) {
            $type = $renderer->supports();
            var_dump($type);

            $orderId = $this->getOrderId($type);

            // skip if no order with document of this type

            if ($orderId === null) {
                return new Result(
                    $this->name(),
                    Status::SKIPPED,
                    'no order found for document preview of type: ' . $type,
                    true,
                    []
                );

                continue;
            }

            var_dump($orderId);

            $operation = new DocumentGenerateOperation(
                $orderId,
                HtmlRenderer::FILE_EXTENSION,
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

            var_dump($processedTemplate->getSuccess());

            $content = $processedTemplate->getSuccess()[$orderId]->getContent();

            if ($content === '') {
                continue;
            }

        }


        return new Result(
            $this->name(),
            Status::OK,
            'a message',
            true,
            []
        );
    }

    private function getOrderId(string $type)
    {
        // some document types depend on invoice document
        if(in_array($type, self::DEPEND_ON_INVOICE, true)) {
            $invoiceOrderId = $this->getOrderWithDocumentType('invoice');
            if($invoiceOrderId) {
                return $invoiceOrderId;
            }

            // credit-notes need credit-items

            return null;
        }

        $orderId = $this->getOrder();

        return $orderId;
    }

    private function getOrderWithDocumentType(string $type)
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

    private function getOrder(): ?string
    {
        $sql = '
            SELECT
                LOWER(HEX(o.id)) as id
            FROM `order` AS o
            ORDER BY o.created_at DESC
            LIMIT 1
        ';

        $order = $this->connection->fetchOne($sql);
        return $order ?: null;
    }
}
